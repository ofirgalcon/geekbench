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
        $sql = "SELECT machine_desc, machine_model, cpu FROM `machine` WHERE serial_number = '".$this->serial_number."'";
        $machine_data = $queryobj->query($sql);
        $machine_desc = $machine_data[0]->machine_desc;
        $machine_cpu = $machine_data[0]->cpu;
        $machine_model = $machine_data[0]->machine_model;

        // Check if machine is a virutal machine
        if (strpos($machine_desc, 'virtual machine') !== false || strpos($machine_model, 'VMware') !== false){
            print_r("Geekbench module does not support virtual machines, exiting");
            exit(0);
        }

        // Check if we have cached Geekbench JSONs
        $last_cache_pull = munkireport\models\Cache::select('value')->where('module', 'geekbench')->where('property', 'last_cache_pull')->value('value');

        // Get the current time
        $current_time = time();

        // Check if we have a result or a week has passed
        if($last_cache_pull == null || ($current_time > ($last_cache_pull + 104800))){

            // Get JSONs from Geekbench API
            $web_request = new Request();
            $options = ['http_errors' => false];
            $mac_result = (string) $web_request->get('https://browser.geekbench.com/mac-benchmarks.json', $options);
            $cuda_result = (string) $web_request->get('https://browser.geekbench.com/cuda-benchmarks.json', $options);
            $opencl_result = (string) $web_request->get('https://browser.geekbench.com/opencl-benchmarks.json', $options);
            $metal_result = (string) $web_request->get('https://browser.geekbench.com/metal-benchmarks.json', $options);

            // Check if we got results
            if (strpos($mac_result, '"devices": [') === false || strpos($cuda_result, '"devices": [') === false || strpos($opencl_result, '"devices": [') === false){
                print_r("Unable to fetch new JSONs from Geekbench API!!");
            } else {
                // Save new cache data to the cache table
                munkireport\models\Cache::updateOrCreate(
                    ['module' => 'geekbench', 'property' => 'mac_benchmarks',], ['value' => $mac_result, 'timestamp' => $current_time,]
                );
                munkireport\models\Cache::updateOrCreate(
                    ['module' => 'geekbench', 'property' => 'cuda_benchmarks',], ['value' => $cuda_result, 'timestamp' => $current_time,]
                );
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

        $machine_match = ($machine_name.$machine_inch.$machine_year);
        
        $did_match = false;

        // Loop through all benchmarks until match is found
        foreach($benchmarks->devices as $benchmark){

            // Prepare benchmark name for matching
            $name_array = explode("(", $benchmark->name);
            if ( count($name_array) > 1){
            // Extract model, inch, and year
                $benchmark_desc = preg_replace("/[^A-Za-z]/", '', str_replace(array('Server'), array(''), $name_array[0]));
                // Check if benchmark name contains inch
                if (strpos($benchmark->name, '-inch') !== false) {
                    $benchmark_inch = preg_replace("/[^0-9]/", '', explode("-inch", $name_array[1])[0]);
                    // Fix for 27" 5K 2014 iMac, 2013 Macbook Air, 2012 iMac
                    // top is geekbench, bottom is MR format
                    if ($benchmark->name == 'iMac (27-inch Retina)'){
                        $benchmark->name = 'iMac (Retina 27-inch Late 2014)';
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
                    } else if($benchmark->name == 'MacBook Pro (15-inch Mid 2012)'){
                        $benchmark->name = 'MacBook Pro (Retina Mid 2012)';
                        $name_array = explode("(", $benchmark->name);
                    } else if($benchmark->name == 'MacBook Air (Late 2018)'){
                        $benchmark->name = 'MacBook Air (Retina 13-inch 2018)';
                        $name_array = explode("(", $benchmark->name);
                    } else if($benchmark->name == 'MacBook Pro (13-inch Late 2020)'){
                        $benchmark->name = 'MacBook Pro (13-inch M1 2020)';
                        $name_array = explode("(", $benchmark->name);
                    } else if($benchmark->name == "MacBook Air (11-inch Mid 2013)" && strpos($benchmark->description, '4650U') !== false){
                        $benchmark->name = "MacBook Air (11-inch Early 2014)";
                        $name_array = explode("(", $benchmark->name);
                    } else if ($benchmark->name == "iMac (21.5-inch Late 2012)" && strpos($benchmark->description, '3335S') !== false){
                        $benchmark->description = str_replace(array('3335S'), array('3330S'), $benchmark->description);
                    }
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

            $benchmark_match = ($benchmark_desc.$benchmark_inch.$benchmark_year);

            // Process benchmark CPU for matching
            $benchmark_cpu = preg_replace("/[^A-Za-z0-9]/", '', explode("@", $benchmark->description)[0]);

            // Check through for a matching machine description and CPU
            if ( $benchmark_cpu == $machine_cpu){
                
                // Fill in data from matching entry
                $this->score = $benchmark->score;
                $this->multiscore = $benchmark->multicore_score;
                $this->model_name = $benchmark->name;
                $this->description = $benchmark->description;
                $this->samples = $benchmark->samples;
                
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
            $this->gpu_name = str_replace(array('NVIDIA ','Intel ','HD Graphics 3000'), array('','','HD Graphics'), $gpu_model);

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
                $gpu_opencl_benchmark_prepared = str_replace(array('NVIDIA ','(R)','(TM)','Intel '), array('','','',''), $gpu_opencl_benchmark->name);

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
                $gpu_metal_benchmark_prepared = str_replace(array('NVIDIA ','(R)','(TM)','Intel ','AMD '), array('','','','',''), $gpu_metal_benchmark->name);

                // Check through for a matching GPU
                if ($gpu_metal_benchmark_prepared == $this->gpu_name){
                    // Fill in data from matching entry
                    $this->metal_samples = $gpu_metal_benchmark->samples;
                    $this->metal_score = $gpu_metal_benchmark->score;

                    // Exit loop because we found a match
                    break;
                }
            }
        }

        // Save the data if matched
        if($did_match){
            $this->save();
        }
    }
}

