<?php

use CFPropertyList\CFPropertyList;
use munkireport\lib\Request;

class Geekbench_model extends \Model
{
    public function __construct($serial = '')
    {
        // Call the parent constructor with primary key and table name.
        parent::__construct('id', 'geekbench'); // Primary key, tablename
        
        // Initialize record (rs) fields.
        $this->rs['id'] = 0;
        $this->rs['serial_number'] = $serial;
        $this->rs['score'] = null;
        $this->rs['multiscore'] = null;
        $this->rs['model_name'] = null;
        $this->rs['description'] = null;
        $this->rs['samples'] = null;
        $this->rs['cuda_score'] = null;
        $this->rs['cuda_samples'] = null;
        $this->rs['opencl_score'] = null;
        $this->rs['opencl_samples'] = null;
        $this->rs['metal_score'] = null;
        $this->rs['metal_samples'] = null;
        $this->rs['gpu_name'] = null;
        $this->rs['last_run'] = null;
        $this->rs['gpu_cores'] = null;

        // If a serial number is provided, attempt to retrieve the record from the database.
        if ($serial) {
            $this->retrieve_record($serial);
        }

        $this->serial_number = $serial;
    }

    /**
     * Process data sent by postflight
     *
     * @param string data
     * 
     **/
    public function process($data = '')
    {
        // =====================================================
        // MACHINE DATA RETRIEVAL
        // =====================================================
        // Get machine description, model, CPU, and processor count from the machine table.
        $queryobj = $this;
        $sql = "SELECT machine_desc, machine_model, cpu, number_processors FROM `machine` WHERE serial_number = :serial_number";
        $machine_data = $queryobj->query($sql, [':serial_number' => $this->serial_number]);
        $machine_desc = $machine_data[0]->machine_desc;
        $machine_cpu = $machine_data[0]->cpu;
        $machine_model = $machine_data[0]->machine_model;
        $machine_cores = $machine_data[0]->number_processors;
        
        // Exclude virtual machines by checking for specific substrings.
        if (strpos($machine_desc, 'virtual machine') !== false || strpos($machine_model, 'VMware') !== false) {
            print_r("Geekbench module does not support virtual machines, exiting");
            exit(0);
        }

        // =====================================================
        // GPU DATA RETRIEVAL
        // =====================================================
        // Query GPU table to obtain the number of GPU cores.
        $sql_gpu = "SELECT num_cores FROM `gpu` WHERE serial_number = '" . $this->serial_number . "'";
        $gpu_data = $queryobj->query($sql_gpu);
        if (!empty($gpu_data)) {
            $machine_gpu_cores = $gpu_data[0]->num_cores;
        } else {
            $machine_gpu_cores = 0; // Default value when no GPU data is available.
        }

        // =====================================================
        // CACHE CHECK AND DATA FETCHING FROM API
        // =====================================================
        // Get the last time cached Geekbench JSON data was pulled.
        $last_cache_pull = munkireport\models\Cache::select('value')->where('module', 'geekbench')->where('property', 'last_cache_pull')->value('value');

        // Get current time.
        $current_time = time();

        // If no cache exists or it is older than ~1 day, fetch new JSON data.
        if ($last_cache_pull == null || ($current_time > ($last_cache_pull + 87000))) {

            // Fetch Mac, OpenCL, and Metal benchmark JSON data from Geekbench API.
            $web_request = new Request();
            $options = ['http_errors' => false];
            $mac_result = (string) $web_request->get('https://browser.geekbench.com/mac-benchmarks.json', $options);
            // $cuda_result = (string) $web_request->get('https://browser.geekbench.com/cuda-benchmarks.json', $options);
            $opencl_result = (string) $web_request->get('https://browser.geekbench.com/opencl-benchmarks.json', $options);
            $metal_result = (string) $web_request->get('https://browser.geekbench.com/metal-benchmarks.json', $options);

            // Validate that the JSON contains valid device data.
            if (strpos($mac_result, '"devices": [') === false || strpos($opencl_result, '"devices": [') === false) {
                print_r("Unable to fetch new JSONs from Geekbench API!!");
            } else {
                // Update database cache with the new JSON data.
                munkireport\models\Cache::updateOrCreate(
                    ['module' => 'geekbench', 'property' => 'mac_benchmarks',],
                    ['value' => $mac_result, 'timestamp' => $current_time,]
                );
                munkireport\models\Cache::updateOrCreate(
                    ['module' => 'geekbench', 'property' => 'opencl_benchmarks',],
                    ['value' => $opencl_result, 'timestamp' => $current_time,]
                );
                munkireport\models\Cache::updateOrCreate(
                    ['module' => 'geekbench', 'property' => 'metal_benchmarks',],
                    ['value' => $metal_result, 'timestamp' => $current_time,]
                );
                munkireport\models\Cache::updateOrCreate(
                    ['module' => 'geekbench', 'property' => 'last_cache_pull',],
                    ['value' => $current_time, 'timestamp' => $current_time,]
                );
            }
        }

        //
        // =====================================================
        // BEGIN PROCESSING THE FETCHED BENCHMARK DATA
        // =====================================================
        //

        // Retrieve cached JSON strings from the database.
        $mac_benchmarks_json = munkireport\models\Cache::select('value')->where('module', 'geekbench')->where('property', 'mac_benchmarks')->value('value');
        // $cuda_benchmarks_json = munkireport\models\Cache::select('value')->where('module', 'geekbench')->where('property', 'cuda_benchmarks')->value('value');
        $opencl_benchmarks_json = munkireport\models\Cache::select('value')->where('module', 'geekbench')->where('property', 'opencl_benchmarks')->value('value');
        $metal_benchmarks_json = munkireport\models\Cache::select('value')->where('module', 'geekbench')->where('property', 'metal_benchmarks')->value('value');

        // Decode the JSON data into usable PHP objects.
        $benchmarks = json_decode($mac_benchmarks_json);
        // $gpu_cuda_benchmarks = json_decode($cuda_benchmarks_json);
        $gpu_opencl_benchmarks = json_decode($opencl_benchmarks_json);
        $gpu_metal_benchmarks = json_decode($metal_benchmarks_json);

        // =====================================================
        // MACHINE CPU STRING PREPARATION
        // =====================================================
        // Clean up the CPU string from unnecessary characters and text.
        $machine_cpu = preg_replace("/[^A-Za-z0-9]/", '', explode("@", str_replace(array('(R)', 'CPU ', '(TM)2', '(TM)', 'Core2'), array('', '', ' 2', '', 'Core 2'), $machine_cpu))[0]);

        // Special handling for older MacBook models.
        if (strpos($machine_desc, 'MacBook (13-inch, ') !== false) {
            $machine_desc = str_replace(array('13-inch, '), array(''), $machine_desc);
        }

        // =====================================================
        // MACHINE DESCRIPTION PARSING FOR MATCHING
        // =====================================================
        // Split the machine description into components: base name, screen size, and year.
        $desc_array = explode("(", $machine_desc);
        if (count($desc_array) > 1) {
            // Clean the base model name.
            $machine_name = preg_replace("/[^A-Za-z]/", '', str_replace(array('Server'), array(''), $desc_array[0]));
            // Extract screen size if available.
            if (strpos($machine_desc, '-inch') !== false) {
                $machine_inch = preg_replace("/[^0-9]/", '', explode("-inch", str_replace(array('5K'), array(''), $desc_array[1]))[0]);
            } else {
                $machine_inch = "";
            }
            // Extract year from the description if present.
            if (strpos($machine_desc, ' 20') !== false) {
                $machine_year = "20" . preg_replace("/[^0-9]/", '', explode(", ", explode(" 20", str_replace(array('5K'), array(''), $desc_array[1]))[1])[0]);
            } else {
                $machine_year = "";
            }
        } else {
            // Fix for other machines without a year in their name
            $machine_name = preg_replace("/[^A-Za-z]/", '', str_replace(array('Server'), array(''), $desc_array[0]));
            $machine_inch = "";
            $machine_year = "";
        }

        // Concatenate the machine name and screen size to form the matching identifier.
        $machine_match = ($machine_name . $machine_inch);

        $did_match = false;
        $got_opencl_score = false;
        $got_metal_score = false;

        // =====================================================
        // LOOP THROUGH EACH BENCHMARK TO FIND A MATCH
        // =====================================================
        $exact_match_found = false;
        $fallback_candidate = null;

        foreach ($benchmarks->devices as $benchmark) {

            // -------------------------
            // Prepare Benchmark Name for Matching
            // -------------------------
            $name_array = explode("(", $benchmark->name);
            if (count($name_array) > 1) {
                // Extract the base benchmark model name.
                $benchmark_desc = preg_replace("/[^A-Za-z]/", '', str_replace(array('Server'), array(''), $name_array[0]));

                // =====================================================
                // NAME MAPPING TO NORMALIZE MODEL NAMES (ESSENTIAL)
                // =====================================================
                $nameMapping = [
                    'iMac (27-inch Retina)' => 'iMac (Retina 27-inch Late 2014)',
                    'MacBook Pro (15-inch Mid 2012)' => 'MacBook Pro (15-inch, Mid 2012)',
                    'iMac (21.5-inch Retina Early 2019)' => 'iMac (Retina 4K, 21.5-inch, 2019)',
                    'MacBook Air (Mid 2017)' => 'MacBook Air (13-inch 2017)',
                    'iMac (21.5-inch Retina Mid 2017)' => 'iMac (Retina 4K, 21.5-inch, 2017)',
                    'MacBook Air (Early 2020)' => 'MacBook Air (Retina, 13-inch, 2020)',
                    'MacBook Pro (15-inch Mid 2019)' => 'MacBook Pro (15-inch 2019)',
                    'MacBook (Mid 2017)' => 'MacBook (Retina 12-inch 2017)',
                    'MacBook Air (Late 2018)' => 'MacBook Air (Retina 13-inch 2018)',
                    'iMac Pro (Late 2017)' => 'iMac Pro (2017)',
                    'MacBook Air (Late 2020)' => 'MacBook Air (M1, 2020)',
                    'MacBook Air (11-inch Mid 2013)' => 'MacBook Air (11-inch Early 2014)',
                ];

                // If the benchmark name exists in the mapping, update it accordingly.
                if (isset($nameMapping[$benchmark->name])) {
                    $benchmark->name = $nameMapping[$benchmark->name];
                }

                // Special case handling for a specific 2013 MacBook Air.
                if ($benchmark->name == 'MacBook Air (11-inch Mid 2013)' && strpos($benchmark->description, '4650U') !== false) {
                    $benchmark->name = 'MacBook Air (11-inch Early 2014)';
                }

                // Specific adjustment for an iMac description error.
                if ($benchmark->name == 'iMac (21.5-inch Late 2012)' && strpos($benchmark->description, '3335S') !== false) {
                    $benchmark->description = str_replace('3335S', '3330S', $benchmark->description);
                }

                // Re-split the benchmark name after any mapping adjustments.
                $name_array = explode("(", $benchmark->name);

                // =====================================================
                // Extract Benchmark Screen Size and Year
                // =====================================================
                if (strpos($benchmark->name, '-inch') !== false) {
                    $benchmark_inch = preg_replace("/[^0-9]/", '', explode("-inch", $name_array[1])[0]);
                } else {
                    $benchmark_inch = "";
                }

                // Check if benchmark name contains year
                if (strpos($benchmark->name, ' 20') !== false) {
                    $benchmark_year = "20" . preg_replace("/[^0-9]/", '', explode(", ", explode(" 20", $name_array[1])[1])[0]);
                } else {
                    $benchmark_year = "";
                }
            } else {
                // Fix machines without a year in their name
                $benchmark_desc = preg_replace("/[^A-Za-z]/", '', str_replace(array('Server'), array(''), $name_array[0]));
                $benchmark_inch = "";
                $benchmark_year = "";
            }

            // Special fix for a known JSON error with a specific benchmark description.
            if ($benchmark->description === "Apple M1 Pro @ 3.2 GHz (10 CPU cores, 10 GPU cores)") {
                $benchmark->description = "Apple M1 Pro @ 3.2 GHz (10 CPU cores, 16 GPU cores)";
            }

            // Construct the benchmark matching identifier from the cleaned description and screen size.
            $benchmark_match = ($benchmark_desc . $benchmark_inch);

            // -------------------------
            // Process Benchmark CPU and Core Details
            // -------------------------
            $benchmark_cpu = preg_replace("/[^A-Za-z0-9]/", '', explode("@", $benchmark->description)[0]);
            $benchmark_cores = preg_replace("/[^0-9]/", '', explode("GHz", $benchmark->description)[1]);
            // $benchmark_cores = substr($benchmark_cores, 0, 1);

            $benchmark_gpu_cores_pattern = '/(\d+)\s+GPU cores/';
            if (preg_match($benchmark_gpu_cores_pattern, $benchmark->description, $matches)) {
                $benchmark_gpu_cores = intval($matches[1]);
            } else {
                $benchmark_gpu_cores = 0;
            }

            // Direct matches for specific values
            $special_cases = ["88", "87", "810"];
            if (in_array($benchmark_cores, $special_cases)) {
                $benchmark_cores = "8";
            } else {
                // Two-digit prefixes to check
                $prefixes = ["10", "11", "12", "14", "16", "18", "19", "20", "24", "30", "32", "38", "40", "48", "60", "76"];
                foreach ($prefixes as $prefix) {
                    if (substr($benchmark_cores, 0, 2) === $prefix) {
                        $benchmark_cores = $prefix;
                        break;
                    }
                }
            }

            if ($benchmark_gpu_cores === 0) {
                $benchmark_gpu_cores = $machine_gpu_cores;
            }

            // -------------------------
            // PRIMARY MATCHING: Exact Match Check
            // -------------------------
            if ($benchmark_match == $machine_match && $benchmark_cpu == $machine_cpu && $benchmark_cores == $machine_cores && $benchmark_gpu_cores == $machine_gpu_cores) {
                $this->score = $benchmark->score;
                $this->multiscore = $benchmark->multicore_score;
                $this->model_name = $benchmark->name;
                $this->description = $benchmark->description;
                $this->samples = $benchmark->samples;
                
                // Store OpenCL and Metal scores from mac_benchmarks if they exist
                if (isset($benchmark->opencl) && !empty($benchmark->opencl)) {
                    $this->opencl_score = $benchmark->opencl;
                    $this->opencl_samples = $benchmark->samples;
                    $got_opencl_score = true;
                }
                if (isset($benchmark->metal) && !empty($benchmark->metal)) {
                    $this->metal_score = $benchmark->metal;
                    $this->metal_samples = $benchmark->samples;
                    $got_metal_score = true;
                }
                
                $exact_match_found = true;
                $did_match = true;
            }
            // -------------------------
            // SECONDARY MATCHING: Record Fallback Candidate
            // -------------------------
            else if ($benchmark_cpu == $machine_cpu &&
                     $benchmark_cores == $machine_cores &&
                     $benchmark_gpu_cores == $machine_gpu_cores) {
                // Record the first encountered fallback candidate
                if ($fallback_candidate === null) {
                    $fallback_candidate = $benchmark;
                }
            }
        }

        // Only use fallback if no exact match was found after checking all entries
        if (!$exact_match_found && !is_null($fallback_candidate)) {
            $this->score = $fallback_candidate->score;
            $this->multiscore = $fallback_candidate->multicore_score;
            $this->model_name = $fallback_candidate->name . "*";  // Mark fallback with an asterisk
            $this->description = $fallback_candidate->description;
            $this->samples = $fallback_candidate->samples;
            $this->cuda_metal = $fallback_candidate->metal;
            $did_match = true;
        }

        // =====================================================
        // RECORD TIMING AND GPU MODEL HANDLING
        // =====================================================
        // Set the last_run timestamp to the current time.
        $this->last_run = time();

        $gpu_model = "";

        // If no new data is provided, determine the GPU model from previous data or another module.
        if ($data == "" && !is_null($this->rs["gpu_name"])) {
            $gpu_model = $this->rs["gpu_name"];
        } else if ($data == "" && is_null($this->rs["gpu_name"])) {
            // If no GPU name is present, create a new Gpu_model to retrieve it.
            $gpu = new Gpu_model($this->serial_number);
            $gpu_model = $gpu->rs["model"];
        }

        // If new data is provided or a GPU model is available, process GPU benchmark data.
        if ($data !== "" || $gpu_model !== "") {
            // If we have data, use that
            if ($data != "") {
                // Process the incoming plist data.
                $parser = new CFPropertyList();
                $parser->parse($data);
                $plist = $parser->toArray();

                // Update GPU model and last_run timestamp from the plist.
                $gpu_model = $plist["model"];
                $this->last_run = $plist["last_run"];
            }

            // Standardize the GPU model name by removing vendor-specific prefixes.
            $this->gpu_name = str_replace(array('AMD ', 'NVIDIA ', 'Intel ', 'HD Graphics 3000', 'ATI '), array('', '', '', 'HD Graphics', ''), $gpu_model);

            // Loop through all GPU CUDA benchmarks until match is found
            // foreach ($gpu_cuda_benchmarks->devices as $gpu_cuda_benchmark) {

            //     // Check through for a matching GPU
            //     if ($gpu_cuda_benchmark->name == $this->gpu_name) {

            //         // Fill in data from matching entry
            //         $this->cuda_samples = $gpu_cuda_benchmark->samples;
            //         $this->cuda_score = $gpu_cuda_benchmark->score;

            //         // Exit loop because we found a match
            //         break;
            //     }
            // }

            // =====================================================
            // GPU OPENCL BENCHMARK MATCHING
            // =====================================================
            if (!$got_opencl_score) {
                foreach ($gpu_opencl_benchmarks->devices as $gpu_opencl_benchmark) {
                    // Prepare the GPU name by removing extraneous text.
                    $gpu_opencl_benchmark_prepared = str_replace(array('NVIDIA ', '(R)', '(TM)', 'Intel ', 'AMD ', 'Radeon HD - ', ' Compute Engine', 'ATI '), array('', '', '', '', '', '', '', ''), $gpu_opencl_benchmark->name);

                    // If a match is found, store the OpenCL benchmark values.
                    if ($gpu_opencl_benchmark_prepared == $this->gpu_name) {
                        // Don't set OpenCL scores for older AMD GPUs as they'll use Metal scores instead
                        if (strpos($gpu_model, 'AMD') === false || strpos($gpu_model, 'RX') !== false || strpos($gpu_model, 'Vega') !== false) {
                            $this->opencl_samples = $gpu_opencl_benchmark->samples;
                            $this->opencl_score = $gpu_opencl_benchmark->score;
                        }
                        break;
                    }
                }
            }

            // =====================================================
            // GPU METAL BENCHMARK MATCHING
            // =====================================================
            if (!$got_metal_score) {
                foreach ($gpu_metal_benchmarks->devices as $gpu_metal_benchmark) {
                    // Prepare the GPU model name for matching.
                    $gpu_metal_benchmark_prepared = str_replace(array('NVIDIA ', '(R)', '(TM)', 'Intel ', 'AMD ', 'Radeon HD - '), array('', '', '', '', '', ''), $gpu_metal_benchmark->name);

                    if ($gpu_metal_benchmark_prepared == 'Iris Graphics 6000') {
                        $gpu_metal_benchmark_prepared = 'HD Graphics 6000';
                    }

                    if ($gpu_metal_benchmark_prepared == 'Iris Graphics') {
                        $gpu_metal_benchmark_prepared = 'Iris';
                    }

                    if ($gpu_metal_benchmark_prepared == 'Iris Pro Graphics') {
                        $gpu_metal_benchmark_prepared = 'Iris Pro';
                    }

                    // If the prepared GPU model matches, store the Metal benchmark values.
                    if ($gpu_metal_benchmark_prepared == $this->gpu_name) {
                        $this->metal_samples = $gpu_metal_benchmark->samples;
                        $this->metal_score = $gpu_metal_benchmark->score;
                        
                        // For older AMD GPUs, use Metal scores for OpenCL as well
                        if (strpos($gpu_model, 'AMD') !== false && strpos($gpu_model, 'RX') === false && strpos($gpu_model, 'Vega') === false) {
                            $this->opencl_samples = $gpu_metal_benchmark->samples;
                            $this->opencl_score = $gpu_metal_benchmark->score;
                        }
                        
                        break;
                    }
                }
            }
        }

        // =====================================================
        // SAVE MATCHED DATA
        // =====================================================
        // If a benchmark match was found (exact or fallback), save the record.
        if ($did_match) {
            $this->save();
        }
    }
}
