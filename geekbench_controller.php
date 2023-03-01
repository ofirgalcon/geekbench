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
} // END class Geekbench_controller
