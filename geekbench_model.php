<?php

use CFPropertyList\CFPropertyList;

class Geekbench_model extends \Model
{
    public function __construct($serial = '')
    {
        parent::__construct('id', 'geekbench'); // Primary key, tablename
        $this->rs['id'] = 0;
        $this->rs['serial_number'] = $serial;
        $this->rs['score'] = '';
        $this->rs['multiscore'] = '';
        $this->rs['model_name'] = '';
        $this->rs['description'] = '';
        $this->rs['samples'] = '';
        $this->rs['cuda_score'] = null;
        $this->rs['cuda_samples'] = null;
        $this->rs['opencl_score'] = null;
        $this->rs['opencl_samples'] = null;
        $this->rs['gpu_name'] = null;
        $this->rs['last_cache_pull'] = null;
        $this->rs['mac_benchmarks'] = '';
        $this->rs['cuda_benchmarks'] = '';
        $this->rs['opencl_benchmarks'] = '';

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
        $machine = new Machine_model($this->serial_number);        
        $machine_desc = $machine->rs["machine_desc"];
        $machine_cpu = $machine->rs["cpu"];

        // Check if machine is a virutal machine
        if (strpos($machine_desc, 'virtual machine') !== false){
            print_r("Geekbench module does not support virtual machines, exiting");
            exit(0);
        }

        // Check if we have cached Geekbench JSONs
        $queryobj = new Geekbench_model();
        $sql = "SELECT last_cache_pull FROM `geekbench` WHERE serial_number = 'JSON_CACHE_DATA'";
        $cached_data = $queryobj->query($sql);

        // Get the current time
        $current_time = time();

        // Check if we have a result or a week has passed
        if($cached_data == null || ($current_time > ($cached_data[0]->last_cache_pull + 604800))){

            // Get JSONs from Geekbench API
            ini_set("allow_url_fopen", 1);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_URL, 'https://browser.geekbench.com/mac-benchmarks.json');
            $mac_result = curl_exec($ch);
            curl_setopt($ch, CURLOPT_URL, 'https://browser.geekbench.com/cuda-benchmarks.json');
            $cuda_result = curl_exec($ch);
            curl_setopt($ch, CURLOPT_URL, 'https://browser.geekbench.com/opencl-benchmarks.json');
            $opencl_result = curl_exec($ch);

            // Check if we got results
            if (strpos($mac_result, '"devices": [') === false || strpos($cuda_result, '"devices": [') === false || strpos($opencl_result, '"devices": [') === false){
                print_r("Unable to fetch new JSONs from Geekbench API!!");
            } else {                                
                // Delete old cached data
                $sql = "DELETE FROM `geekbench` WHERE serial_number = 'JSON_CACHE_DATA';";
                $queryobj->exec($sql);

                // Insert new cached data
                $sql = "INSERT INTO `geekbench` (serial_number,description,last_cache_pull,mac_benchmarks,cuda_benchmarks,opencl_benchmarks) 
                        VALUES ('JSON_CACHE_DATA','Do not delete this row','".$current_time."','".$mac_result."','".$cuda_result."','".$opencl_result."')";
                $queryobj->exec($sql);
            }
        }

        //
        //
        // Start of the processing of the data
        //
        //

        // Get the cached JSONs from the database
        $sql = "SELECT mac_benchmarks, cuda_benchmarks, opencl_benchmarks FROM `geekbench` WHERE serial_number = 'JSON_CACHE_DATA'";
        $cached_jsons = $queryobj->query($sql);

        // Decode JSON
        $benchmarks = json_decode($cached_jsons[0]->mac_benchmarks);
        $gpu_cuda_benchmarks = json_decode($cached_jsons[0]->cuda_benchmarks);
        $gpu_opencl_benchmarks = json_decode($cached_jsons[0]->opencl_benchmarks);
        
        // Prepare machine CPU type string for matching
        $machine_cpu = preg_replace("/[^A-Za-z0-9]/", '', explode("@", str_replace(array('(R)','CPU ','(TM)2','(TM)','Core2'), array('','',' 2','','Core 2'), $machine_cpu))[0]);

        // Prepare machine description for matching
        $desc_array = explode("(", $machine_desc);
        if ( count($desc_array) > 1){
            $machine_desc = preg_replace("/[^A-Za-z0-9]/", '', $desc_array[0]).preg_replace("/[^0-9]/", '', $desc_array[1]);
        } else {
            $machine_desc = preg_replace("/[^A-Za-z0-9]/", '', $desc_array[0]);
        }

        // Loop through all benchmarks until match is found
        foreach($benchmarks->devices as $benchmark){

            // Prepare benchmark name for matching
            $name_array = explode("(", $benchmark->name);
            if ( count($name_array) > 1){
                $benchmark_name = preg_replace("/[^A-Za-z0-9]/", '', $name_array[0]).preg_replace("/[^0-9]/", '', $name_array[1]);
            } else {
                $benchmark_name = preg_replace("/[^A-Za-z0-9]/", '', $name_array[0]);
            }

            // Process benchmark CPU for matching
            $benchmark_cpu = preg_replace("/[^A-Za-z0-9]/", '', explode("@", $benchmark->description)[0]);

            // Check through for a matching machine description and CPU
            if ($benchmark_name == $machine_desc && $benchmark_cpu == $machine_cpu){
                // Fill in data from matching entry
                $this->score = $benchmark->score;
                $this->multiscore = $benchmark->multicore_score;
                $this->model_name = $benchmark->name;
                $this->description = $benchmark->description;
                $this->samples = $benchmark->samples;

                // Exit loop because we found a match
                break;
            }
        }
        
        // Insert last ran timestamp, may be overwritten by $data
        $this->last_cache_pull = time();

        $gpu_model = "";
        
        // Check if we have data so we can process GPU
        if ($data == "" && !is_null($this->rs["gpu_name"])){
            // If we don't have data, use existing GPU model
            $gpu_model = $this->rs["gpu_name"];
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
                $this->last_cache_pull = $plist["last_run"];
            }
            
            
            $this->gpu_name = $gpu_model;

            // Loop through all GPU CUDA benchmarks until match is found
            foreach($gpu_cuda_benchmarks->devices as $gpu_cuda_benchmark){

                // Prepare gpu model for matching
                $gpu_cuda_benchmark_prepared = str_replace(array('(R)'), array(''), $gpu_cuda_benchmark->name);

                // Check through for a matching GPU
                if ($gpu_cuda_benchmark_prepared == $gpu_model){

                    // Fill in data from matching entry
                    $this->cuda_samples = $gpu_cuda_benchmark->samples;
                    $this->cuda_score = $gpu_cuda_benchmark->score;

                    // Exit loop because we found a match
                    break;
                }
            }

            // Loop through all GPU OpenCL benchmarks until match is found
            foreach($gpu_opencl_benchmarks->devices as $gpu_opencl_benchmark){

                // Check through for a matching GPU
                if ($gpu_opencl_benchmark->name == $gpu_model){

                    // Fill in data from matching entry
                    $this->opencl_samples = $gpu_opencl_benchmark->samples;
                    $this->opencl_score = $gpu_opencl_benchmark->score;

                    // Exit loop because we found a match
                    break;
                }
            }
        }

        // Save the data
        $this->save();
    }
}

