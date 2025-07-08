<?php

use munkireport\lib\Request;

/**
 * geekbench_controller class
 *
 * @package munkireport
 * @author AvB
 **/
class Geekbench_controller extends Module_controller
{
    public function __construct()
    {
        $this->module_path = dirname(__FILE__);
    }

    /**
     * Default method
     *
     * @author AvB
     **/
    public function index()
    {
        echo "You've loaded the geekbench module!";
    }
    
    public function admin()
    {
        $obj = new View();
        $obj->view('geekbench_admin', [], $this->module_path.'/views/');
    }
        
    /**
     * Force data pull from Geekbench
     *
     * @return void
     * @author tuxudo
     **/
    public function update_cached_jsons()
    {
        // Get JSONs from Geekbench API
        $web_request = new Request();
        $options = ['http_errors' => false];
        $mac_result = (string) $web_request->get('https://browser.geekbench.com/mac-benchmarks.json', $options);
        // $cuda_result = (string) $web_request->get('https://browser.geekbench.com/cuda-benchmarks.json', $options);
        $opencl_result = (string) $web_request->get('https://browser.geekbench.com/opencl-benchmarks.json', $options);
        $metal_result = (string) $web_request->get('https://browser.geekbench.com/metal-benchmarks.json', $options);

        // Check if we got results
        if (strpos($mac_result, '"devices": [') === false || strpos($opencl_result, '"devices": [') === false){
            
            // Send result
            jsonView(array("status"=>0));

        } else {

            // Get the current time
            $current_time = time();

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
            
            // Clear the max_scores cache so it will be recalculated on next request
            munkireport\models\Cache::where('module', 'geekbench')
                ->where('property', 'max_scores')
                ->delete();

            // Send result
            jsonView(array("status"=>1));
        }
    }
    
    /**
     * Pull in Geekbench data for all serial numbers :D
     *
     * @return void
     * @author tuxudo
     **/
    public function pull_all_geekbench_data($incoming_serial = '')
    {
        // Check if we are returning a list of all serials or processing a serial
        // Returns either a list of all serial numbers in MunkiReport OR
        // a JSON of what serial number was just ran with the status of the run
        if ( $incoming_serial == ''){
            // Get all the serial numbers in an object
            $machine = new Geekbench_model();
            $filter = get_machine_group_filter();

            $sql = "SELECT machine.serial_number
                    FROM machine
                    LEFT JOIN reportdata USING (serial_number)
                    $filter";

            // Loop through each serial number for processing
            $out = array();
            foreach ($machine->query($sql) as $serialobj) {
                $out[] = $serialobj->serial_number;
            }
            jsonView($out);
        } else {

            // Check if machine is a virutal machine
            $queryobj = new Geekbench_model();
            $sql = "SELECT machine_desc, machine_model FROM `machine` WHERE serial_number = '".$incoming_serial."'";
            $machine_data = $queryobj->query($sql);
            $machine_desc = $machine_data[0]->machine_desc;
            $machine_model = $machine_data[0]->machine_model;

            if (strpos($machine_desc, 'virtual machine') !== false || strpos($machine_model, 'VMware') !== false){
                $out = array("serial"=>$incoming_serial,"process_status"=>"Virtual machine skipped");
                jsonView($out);
            } else if ($machine_model == "" || $machine_model == null){
                $out = array("serial"=>$incoming_serial,"process_status"=>"Skipping machine, does not exist");
                jsonView($out);
            } else {
                $geekbench = new Geekbench_model($incoming_serial);
                $geekbench_status = $geekbench->process();
                // Check if machine matched
                if ($geekbench->rs["score"] == "" || $geekbench->rs["score"] == null){
                    $out = array("serial"=>$incoming_serial,"process_status"=>"Machine not matched");
                    jsonView($out);
                } else {
                    $out = array("serial"=>$incoming_serial,"process_status"=>"Machine processed");
                    jsonView($out);
                }
            }
        }
    }

    /**
     * Force data pull from Geekbench
     *
     * @return void
     * @author tuxudo
     **/
    public function recheck_geekbench($serial = '')
    {
        // Load model and lookup scores
        if (authorized_for_serial($serial)) {
            $geekbench = new Geekbench_model($serial);
            $geekbench->process();
        }
        
        // Send people back to the client tab once scores are pulled
        redirect("clients/detail/$serial#tab_geekbench-tab");
    }

    /**
     * Retrieve data in json format
     *
     **/
    public function get_data($serial_number = '')
    {
        $geekbench = new Geekbench_model($serial_number);        
        jsonView($geekbench->rs);
    }
    
    /**
     * Get maximum scores from cached benchmark data
     *
     * @return void
     * @author [Your Name]
     **/
    public function get_max_scores()
    {
        // Initialize result array with default values
        $result = [
            'score' => 1000,
            'multiscore' => 10000,
            'metal_score' => 100000,
            'opencl_score' => 100000,
            'cuda_score' => 100000
        ];
        
        try {
            // Check if we already have cached max scores and when they were last updated
            $cached_max_scores = munkireport\models\Cache::select('value', 'timestamp')
                ->where('module', 'geekbench')
                ->where('property', 'max_scores')
                ->first();
                
            // Get the timestamp of when the Mac benchmarks were last updated
            $mac_benchmarks_timestamp = munkireport\models\Cache::select('timestamp')
                ->where('module', 'geekbench')
                ->where('property', 'mac_benchmarks')
                ->value('timestamp');
            
            // If we have cached max scores and they're newer than the Mac benchmarks data,
            // use the cached values instead of recalculating
            if ($cached_max_scores && 
                $mac_benchmarks_timestamp && 
                $cached_max_scores->timestamp >= $mac_benchmarks_timestamp) {
                
                // Use the cached max scores
                $cached_values = json_decode($cached_max_scores->value, true);
                if (is_array($cached_values)) {
                    return jsonView($cached_values);
                }
            }
            
            // If we don't have cached max scores or they're outdated, calculate them
            
            // Retrieve the Mac benchmarks JSON from the database
            $mac_benchmarks_json = munkireport\models\Cache::select('value')
                ->where('module', 'geekbench')
                ->where('property', 'mac_benchmarks')
                ->value('value');
            
            // Process Mac benchmarks for all scores
            if ($mac_benchmarks_json) {
                $benchmarks = json_decode($mac_benchmarks_json);
                if (isset($benchmarks->devices) && is_array($benchmarks->devices)) {
                    // Initialize max values
                    $max_score = 1000;
                    $max_multiscore = 10000;
                    $max_metal_score = 100000;
                    $max_opencl_score = 100000;
                    
                    // Find maximum scores in a single pass
                    foreach ($benchmarks->devices as $device) {
                        // Single-core score
                        if (isset($device->score) && $device->score > $max_score) {
                            $max_score = $device->score;
                        }
                        
                        // Multi-core score
                        if (isset($device->multicore_score) && $device->multicore_score > $max_multiscore) {
                            $max_multiscore = $device->multicore_score;
                        }
                        
                        // Metal score
                        if (isset($device->metal) && $device->metal > $max_metal_score) {
                            $max_metal_score = $device->metal;
                        }
                        
                        // OpenCL score
                        if (isset($device->opencl) && $device->opencl > $max_opencl_score) {
                            $max_opencl_score = $device->opencl;
                        }
                    }
                    
                    // Update result with found maximum values
                    $result['score'] = $max_score;
                    $result['multiscore'] = $max_multiscore;
                    $result['metal_score'] = $max_metal_score;
                    $result['opencl_score'] = $max_opencl_score;
                    
                    // Cache the calculated max scores
                    $current_time = time();
                    munkireport\models\Cache::updateOrCreate(
                        ['module' => 'geekbench', 'property' => 'max_scores'],
                        ['value' => json_encode($result), 'timestamp' => $current_time]
                    );
                }
            }
            
        } catch (Exception $e) {
            // If an error occurs, use default values
        }
        
        jsonView($result);
    }
    
    /**
     * Get count of models in the JSON cache
     *
     * @return void
     * @author Claude
     **/
    public function get_model_count()
    {
        $result = [
            'mac_count' => 0,
            'opencl_count' => 0,
            'metal_count' => 0,
            'total_count' => 0,
            'last_updated' => 0
        ];
        
        try {
            // Get the last time cached Geekbench JSON data was pulled
            $last_cache_pull = munkireport\models\Cache::select('value')
                ->where('module', 'geekbench')
                ->where('property', 'last_cache_pull')
                ->value('value');
                
            if ($last_cache_pull) {
                $result['last_updated'] = $last_cache_pull;
            }
            
            // Retrieve the Mac benchmarks JSON from the database
            $mac_benchmarks_json = munkireport\models\Cache::select('value')
                ->where('module', 'geekbench')
                ->where('property', 'mac_benchmarks')
                ->value('value');
                
            // Retrieve the OpenCL benchmarks JSON from the database
            $opencl_benchmarks_json = munkireport\models\Cache::select('value')
                ->where('module', 'geekbench')
                ->where('property', 'opencl_benchmarks')
                ->value('value');
                
            // Retrieve the Metal benchmarks JSON from the database
            $metal_benchmarks_json = munkireport\models\Cache::select('value')
                ->where('module', 'geekbench')
                ->where('property', 'metal_benchmarks')
                ->value('value');
            
            // Count Mac models
            if ($mac_benchmarks_json) {
                $benchmarks = json_decode($mac_benchmarks_json);
                if (isset($benchmarks->devices) && is_array($benchmarks->devices)) {
                    $result['mac_count'] = count($benchmarks->devices);
                }
            }
            
            // Count OpenCL models
            if ($opencl_benchmarks_json) {
                $benchmarks = json_decode($opencl_benchmarks_json);
                if (isset($benchmarks->devices) && is_array($benchmarks->devices)) {
                    $result['opencl_count'] = count($benchmarks->devices);
                }
            }
            
            // Count Metal models
            if ($metal_benchmarks_json) {
                $benchmarks = json_decode($metal_benchmarks_json);
                if (isset($benchmarks->devices) && is_array($benchmarks->devices)) {
                    $result['metal_count'] = count($benchmarks->devices);
                }
            }
            
            // Calculate total count
            $result['total_count'] = $result['mac_count'] + $result['opencl_count'] + $result['metal_count'];
            
            // Return the counts
            jsonView($result);
            
        } catch (Exception $e) {
            // Return empty result on error
            jsonView($result);
        }
    }
} // END class Geekbench_controller
