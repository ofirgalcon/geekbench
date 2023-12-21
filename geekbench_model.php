<?php

use CFPropertyList\CFPropertyList;
use munkireport\lib\Request;

class Geekbench_model extends \Model
{
    public function __construct($serial = '')
    {
        parent::__construct('id', 'geekbench'); // Primary key, tablename
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
        // Get machine machine_desc and CPU from machine table
        $queryobj = new Geekbench_model();
        $sql = "SELECT machine_desc, machine_model, cpu, number_processors FROM `machine` WHERE serial_number = '".$this->serial_number."'";
        $machine_data = $queryobj->query($sql);
        $machine_desc = $machine_data[0]->machine_desc;
        $machine_cpu = $machine_data[0]->cpu;
        $machine_model = $machine_data[0]->machine_model;
        $machine_cores = $machine_data[0]->number_processors;
        // Check if machine is a virutal machine
        if (strpos($machine_desc, 'virtual machine') !== false || strpos($machine_model, 'VMware') !== false){
            print_r("Geekbench module does not support virtual machines, exiting");
            exit(0);
        }

        // Get GPU cores from gpu table
        $sql_gpu = "SELECT num_cores FROM `gpu` WHERE serial_number = '".$this->serial_number."'";
        $gpu_data = $queryobj->query($sql_gpu);
        if (!empty($gpu_data)) {
            $machine_gpu_cores = $gpu_data[0]->num_cores;
        } else {
            $machine_gpu_cores = 0; // Default value if no GPU data is found
        }

        // Check if we have cached Geekbench JSONs
        $last_cache_pull = munkireport\models\Cache::select('value')->where('module', 'geekbench')->where('property', 'last_cache_pull')->value('value');

        // Get the current time
        $current_time = time();

        // Check if we have a result or a day (and a little) has passed
        if($last_cache_pull == null || ($current_time > ($last_cache_pull + 87000))){

            // Get JSONs from Geekbench API
            $web_request = new Request();
            $options = ['http_errors' => false];
            $mac_result = (string) $web_request->get('https://browser.geekbench.com/mac-benchmarks.json', $options);
            // $cuda_result = (string) $web_request->get('https://browser.geekbench.com/cuda-benchmarks.json', $options);
            $opencl_result = (string) $web_request->get('https://browser.geekbench.com/opencl-benchmarks.json', $options);
            $metal_result = (string) $web_request->get('https://browser.geekbench.com/metal-benchmarks.json', $options);

            // Check if we got results
            if (strpos($mac_result, '"devices": [') === false || strpos($opencl_result, '"devices": [') === false){
                print_r("Unable to fetch new JSONs from Geekbench API!!");
            } else {
                // Save new cache data to the cache table
                munkireport\models\Cache::updateOrCreate(
                    ['module' => 'geekbench', 'property' => 'mac_benchmarks',], ['value' => $mac_result, 'timestamp' => $current_time,]
                );
                // munkireport\models\Cache::updateOrCreate(
                //     ['module' => 'geekbench', 'property' => 'cuda_benchmarks',], ['value' => $cuda_result, 'timestamp' => $current_time,]
                // );
                munkireport\models\Cache::updateOrCreate(
                    ['module' => 'geekbench', 'property' => 'opencl_benchmarks',], ['value' => $opencl_result, 'timestamp' => $current_time,]
                );
                munkireport\models\Cache::updateOrCreate(
                    ['module' => 'geekbench', 'property' => 'metal_benchmarks',], ['value' => $metal_result, 'timestamp' => $current_time,]
                );
                munkireport\models\Cache::updateOrCreate(
                    ['module' => 'geekbench', 'property' => 'last_cache_pull',], ['value' => $current_time, 'timestamp' => $current_time,]
                );
            }
        }

        //
        //
        // Start of the processing of the data
        //
        //

        // Get the cached JSONs from the database
        $mac_benchmarks_json = munkireport\models\Cache::select('value')->where('module', 'geekbench')->where('property', 'mac_benchmarks')->value('value');
        $cuda_benchmarks_json = munkireport\models\Cache::select('value')->where('module', 'geekbench')->where('property', 'cuda_benchmarks')->value('value');
        $opencl_benchmarks_json = munkireport\models\Cache::select('value')->where('module', 'geekbench')->where('property', 'opencl_benchmarks')->value('value');
        $metal_benchmarks_json = munkireport\models\Cache::select('value')->where('module', 'geekbench')->where('property', 'metal_benchmarks')->value('value');

        // Decode JSON
        $benchmarks = json_decode($mac_benchmarks_json);
        $gpu_cuda_benchmarks = json_decode($cuda_benchmarks_json);
        $gpu_opencl_benchmarks = json_decode($opencl_benchmarks_json);
        $gpu_metal_benchmarks = json_decode($metal_benchmarks_json);

        // Prepare machine CPU type string for matching
        $machine_cpu = preg_replace("/[^A-Za-z0-9]/", '', explode("@", str_replace(array('(R)','CPU ','(TM)2','(TM)','Core2'), array('','',' 2','','Core 2'), $machine_cpu))[0]);
        
        // Fix older Macbooks
        if(strpos($machine_desc, 'MacBook (13-inch, ') !== false){
            $machine_desc = str_replace(array('13-inch, '), array(''), $machine_desc);
        }

        // Prepare machine description for matching
        $desc_array = explode("(", $machine_desc);
        if ( count($desc_array) > 1){
            // Extract model, inch, and year
            $machine_name = preg_replace("/[^A-Za-z]/", '', str_replace(array('Server'), array(''), $desc_array[0]));
            // Check if machine name contains inch
            if (strpos($machine_desc, '-inch') !== false) {
                $machine_inch = preg_replace("/[^0-9]/", '', explode("-inch", str_replace(array('5K'), array(''), $desc_array[1]))[0]);
            } else {
                $machine_inch = "";
            }
            // Check if machine name contains year
            if (strpos($machine_desc, ' 20') !== false) {
                $machine_year = "20".preg_replace("/[^0-9]/", '', explode(", ", explode(" 20", str_replace(array('5K'), array(''), $desc_array[1]))[1])[0]);
            } else {
                $machine_year = "";
            }
        } else {
            // Fix 2006 Mac Pro or other machines without a year in their name
            $machine_name = preg_replace("/[^A-Za-z]/", '', str_replace(array('Server'), array(''), $desc_array[0]));
            $machine_inch = "";
            $machine_year = "";
        }

//o     $machine_match = ($machine_name.$machine_inch.$machine_year);
        $machine_match = ($machine_name.$machine_inch);

        $did_match = false;

        // Loop through all benchmarks until match is found
        foreach($benchmarks->devices as $benchmark){

            // Prepare benchmark name for matching
            $name_array = explode("(", $benchmark->name);
            if ( count($name_array) > 1){
            // Extract model, inch, and year
                $benchmark_desc = preg_replace("/[^A-Za-z]/", '', str_replace(array('Server'), array(''), $name_array[0]));

                // Fix for name inconsistencies 
                    if ($benchmark->name == 'iMac (27-inch Retina)'){
                        $benchmark->name = 'iMac (Retina 27-inch Late 2014)';
                        $name_array = explode("(", $benchmark->name);
                    } else if($benchmark->name == 'MacBook Pro (15-inch Mid 2012)'){
                        $benchmark->name = 'MacBook Pro (15-inch, Mid 2012)';
                        $name_array = explode("(", $benchmark->name);
                    } else if($benchmark->name == 'iMac (21.5-inch Retina Early 2019)'){
                        $benchmark->name = 'iMac (Retina 4K, 21.5-inch, 2019)';
                        $name_array = explode("(", $benchmark->name);
                    } else if($benchmark->name == 'MacBook Air (Mid 2017)'){
                        $benchmark->name = 'MacBook Air (13-inch 2017)';
                        $name_array = explode("(", $benchmark->name);
                    } else if($benchmark->name == 'iMac (21.5-inch Retina Mid 2017)'){
                        $benchmark->name = 'iMac (Retina 4K, 21.5-inch, 2017)';
                        $name_array = explode("(", $benchmark->name);
                    } else if($benchmark->name == 'MacBook Air (Early 2020)'){
                        $benchmark->name = 'MacBook Air (Retina, 13-inch, 2020) ';
                        $name_array = explode("(", $benchmark->name);
                    } else if($benchmark->name == 'MacBook Pro (15-inch Mid 2019)'){
                        $benchmark->name = 'MacBook Pro (15-inch 2019)';
                        $name_array = explode("(", $benchmark->name);
                    } else if($benchmark->name == 'MacBook (Mid 2017)'){
                        $benchmark->name = 'MacBook (Retina 12-inch 2017)';
                        $name_array = explode("(", $benchmark->name);
                    } else if($benchmark->name == 'MacBook Air (Late 2018)'){
                        $benchmark->name = 'MacBook Air (Retina 13-inch 2018)';
                        $name_array = explode("(", $benchmark->name);
                    } else if($benchmark->name == 'iMac Pro (Late 2017)'){
                        $benchmark->name = 'iMac Pro (2017)';
                        $name_array = explode("(", $benchmark->name);
                    } else if($benchmark->name == 'MacBook Air (Late 2018)'){
                        $benchmark->name = 'MacBook Air (Retina 13-inch 2018)';
                        $name_array = explode("(", $benchmark->name);
                    } else if($benchmark->name == 'MacBook Air (Late 2020)'){
                        $benchmark->name = 'MacBook Air (M1, 2020)';
                        $name_array = explode("(", $benchmark->name);
                    } else if($benchmark->name == "MacBook Air (11-inch Mid 2013)" && strpos($benchmark->description, '4650U') !== false){
                        $benchmark->name = "MacBook Air (11-inch Early 2014)";
                        $name_array = explode("(", $benchmark->name);
                    } else if ($benchmark->name == "iMac (21.5-inch Late 2012)" && strpos($benchmark->description, '3335S') !== false){
                        $benchmark->description = str_replace(array('3335S'), array('3330S'), $benchmark->description);
                    }

                // Check if benchmark name contains inch
                if (strpos($benchmark->name, '-inch') !== false) {
                    $benchmark_inch = preg_replace("/[^0-9]/", '', explode("-inch", $name_array[1])[0]);
                } else {
                    $benchmark_inch = "";
                }

                // Check if benchmark name contains year
                if (strpos($benchmark->name, ' 20') !== false) {
                    $benchmark_year = "20".preg_replace("/[^0-9]/", '', explode(", ", explode(" 20", $name_array[1])[1])[0]);
                } else {
                    $benchmark_year = "";
                }
            } else {
                // Fix 2006 Mac Pro or other machines without a year in their name
                $benchmark_desc = preg_replace("/[^A-Za-z]/", '', str_replace(array('Server'), array(''), $name_array[0]));
                $benchmark_inch = "";
                $benchmark_year = "";
            }

            // Fix for id477 error in json
            if ($benchmark->description === "Apple M1 Pro @ 3.2 GHz (10 CPU cores, 10 GPU cores)") {
                $benchmark->description = "Apple M1 Pro @ 3.2 GHz (10 CPU cores, 16 GPU cores)";
            }
            

//o         $benchmark_match = ($benchmark_desc.$benchmark_inch.$benchmark_year);
            $benchmark_match = ($benchmark_desc.$benchmark_inch);

            // Process benchmark CPU for matching
            $benchmark_cpu = preg_replace("/[^A-Za-z0-9]/", '', explode("@", $benchmark->description)[0]);
            $benchmark_cores = preg_replace("/[^0-9]/", '', explode("GHz",$benchmark->description)[1]);
            // $benchmark_cores = substr($benchmark_cores, 0, 1);

            $benchmark_gpu_cores_pattern = '/(\d+)\s+GPU cores/';
            if (preg_match($benchmark_gpu_cores_pattern, $benchmark->description, $matches)) {
                $benchmark_gpu_cores = intval($matches[1]);
            } else {
                $benchmark_gpu_cores = 0;
            }

            // temporary workaround
            if ($benchmark_cores === "88" || $benchmark_cores === "87" || $benchmark_cores === "810") {
                $benchmark_cores = "8";
            } else if (strlen($benchmark_cores) >= 2 && substr($benchmark_cores, 0, 2) === "10") {
                $benchmark_cores = "10";
            } else if (strlen($benchmark_cores) >= 2 && substr($benchmark_cores, 0, 2) === "11") {
                $benchmark_cores = "11";
            } else if (strlen($benchmark_cores) >= 2 && substr($benchmark_cores, 0, 2) === "12") {
                $benchmark_cores = "12";
            } else if (strlen($benchmark_cores) >= 2 && substr($benchmark_cores, 0, 2) === "14") {
                $benchmark_cores = "14";
            } else if (strlen($benchmark_cores) >= 2 && substr($benchmark_cores, 0, 2) === "18") {
                $benchmark_cores = "18";
            } else if (strlen($benchmark_cores) >= 2 && substr($benchmark_cores, 0, 2) === "19") {
                $benchmark_cores = "19";
            } else if (strlen($benchmark_cores) >= 2 && substr($benchmark_cores, 0, 2) === "20") {
                $benchmark_cores = "20";
            } else if (strlen($benchmark_cores) >= 2 && substr($benchmark_cores, 0, 2) === "24") {
                $benchmark_cores = "24";
            } else if (strlen($benchmark_cores) >= 2 && substr($benchmark_cores, 0, 2) === "30") {
                $benchmark_cores = "30";
            } else if (strlen($benchmark_cores) >= 2 && substr($benchmark_cores, 0, 2) === "38") {
                $benchmark_cores = "38";
            } else if (strlen($benchmark_cores) >= 2 && substr($benchmark_cores, 0, 2) === "40") {
                $benchmark_cores = "40";
            } else if (strlen($benchmark_cores) >= 2 && substr($benchmark_cores, 0, 2) === "60") {
                $benchmark_cores = "60";
            } else if (strlen($benchmark_cores) >= 2 && substr($benchmark_cores, 0, 2) === "76") {
                $benchmark_cores = "76";
            }
            
            if ($benchmark_gpu_cores === 0) {
                $benchmark_gpu_cores = $machine_gpu_cores;
            }

            // Check through for a matching machine description and CPU
            if ($benchmark_match == $machine_match && $benchmark_cpu == $machine_cpu && $benchmark_cores == $machine_cores && $benchmark_gpu_cores == $machine_gpu_cores){
                
                // Fill in data from matching entry
                $this->score = $benchmark->score;
                $this->multiscore = $benchmark->multicore_score;
                $this->model_name = $benchmark->name;
                $this->description = $benchmark->description;
                $this->samples = $benchmark->samples;
                $this->cuda_metal = $benchmark->metal;
                
                $did_match = true;
                
                // Exit loop because we found a match
                break;
            }
        }

        // Insert last ran timestamp, may be overwritten by $data
        $this->last_run = time();

        $gpu_model = "";

        // Fill in GPU information
        // If we don't have data, use existing GPU model
        if ($data == "" && !is_null($this->rs["gpu_name"])){
            $gpu_model = $this->rs["gpu_name"];
        } else if ($data == "" && is_null($this->rs["gpu_name"])){
            // Try to get GPU model from gpu module
            $gpu = new Gpu_model($this->serial_number);        
            $gpu_model = $gpu->rs["model"];
        }

        // If we have data or gpu_model is already set
        if ($data !== "" || $gpu_model !== ""){
            // If we have data, use that
            if($data != ""){
                // Process incoming geekbench.plist
                $parser = new CFPropertyList();
                $parser->parse($data);
                $plist = $parser->toArray();

                // Prepare GPU model
                $gpu_model = $plist["model"];

                // Insert last ran timestamp
                $this->last_run = $plist["last_run"];
            }

            // Clean GPU model
            $this->gpu_name = str_replace(array('AMD ','NVIDIA ','Intel ','HD Graphics 3000','ATI '), array('','','','HD Graphics',''), $gpu_model);

            // Loop through all GPU CUDA benchmarks until match is found
            foreach($gpu_cuda_benchmarks->devices as $gpu_cuda_benchmark){

                // Check through for a matching GPU
                if ($gpu_cuda_benchmark->name == $this->gpu_name){

                    // Fill in data from matching entry
                    $this->cuda_samples = $gpu_cuda_benchmark->samples;
                    $this->cuda_score = $gpu_cuda_benchmark->score;

                    // Exit loop because we found a match
                    break;
                }
            }

            // Loop through all GPU OpenCL benchmarks until match is found
            foreach($gpu_opencl_benchmarks->devices as $gpu_opencl_benchmark){
                
                // Prepare gpu model for matching
                $gpu_opencl_benchmark_prepared = str_replace(array('NVIDIA ','(R)','(TM)','Intel ','AMD ','Radeon HD - ',' Compute Engine','ATI '), array('','','','','','','',''), $gpu_opencl_benchmark->name);

                // Check through for a matching GPU
                if ($gpu_opencl_benchmark_prepared == $this->gpu_name){
                    // Fill in data from matching entry
                    $this->opencl_samples = $gpu_opencl_benchmark->samples;
                    $this->opencl_score = $gpu_opencl_benchmark->score;

                    // Exit loop because we found a match
                    break;
                }
            }

            foreach($gpu_metal_benchmarks->devices as $gpu_metal_benchmark){
                
                // Prepare gpu model for matching
                $gpu_metal_benchmark_prepared = str_replace(array('NVIDIA ','(R)','(TM)','Intel ','AMD ','Radeon HD - '), array('','','','','',''), $gpu_metal_benchmark->name);

                if ($gpu_metal_benchmark_prepared == 'Iris Graphics 6000'){
                    $gpu_metal_benchmark_prepared = 'HD Graphics 6000';
                }

                if ($gpu_metal_benchmark_prepared == 'Iris Graphics'){
                    $gpu_metal_benchmark_prepared = 'Iris';
                }

                if ($gpu_metal_benchmark_prepared == 'Iris Pro Graphics'){
                    $gpu_metal_benchmark_prepared = 'Iris Pro';
                }
                
                if ($gpu_metal_benchmark_prepared == 'Apple Paravirtual device'){
                    $gpu_metal_benchmark_prepared = 'Apple M2';
                }
                // Check through for a matching GPU
                if ($gpu_metal_benchmark_prepared == $this->gpu_name){
                    // Fill in data from matching entry
                    $this->metal_samples = $gpu_metal_benchmark->samples;
                    $this->metal_score = $gpu_metal_benchmark->score;
                    // new metal scores 2023
                    $new_metal = $benchmark->metal;
                    $new_opencl = $benchmark->opencl;
                    if (!empty($new_metal)) {
                        $this->metal_score = $new_metal;
                        $this->metal_samples = null;
                    }
                    if (!empty($new_opencl)) {
                        $this->opencl_score = $new_opencl;
                        $this->opencl_samples = null;
                    }
                    // Exit loop because we found a match
                    break;
                }
            }
        }

        // Debugging output
        // var_dump($benchmark_cores);
        // ob_end_flush();
        // var_dump($machine_cores);
        // print($machine_cores . "<br>");
        
        // $this->cuda_score = 111;
        // $this->cuda_samples = $machine_gpu_cores;
        // $this->cuda_samples = $test_metal;

        // Save the data if matched
        if($did_match){
            $this->save();
        }
    }
}

