<?php

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
        
    /**
     * Force data pull from Geekbench
     *
     * @return void
     * @author tuxudo
     **/
    public function update_cached_jsons()
    {
        // Authenticate
        if (! $this->authorized()) {
            die('Authenticate first.'); // Todo: return json?
        }
        
        // Get the current time
        $current_time = time();
        
        $obj = new View();
        $queryobj = new Geekbench_model();
        
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
            
            // Send result
            $out = array("status"=>0);
            $obj->view('json', array('msg' => $out));
            
        } else {                                
            // Delete old cached data
            $sql = "DELETE FROM `geekbench` WHERE serial_number = 'JSON_CACHE_DATA';";
            $queryobj->exec($sql);

            // Insert new cached data
            $sql = "INSERT INTO `geekbench` (serial_number,description,last_cache_pull,mac_benchmarks,cuda_benchmarks,opencl_benchmarks) 
                    VALUES ('JSON_CACHE_DATA','Do not delete this row','".$current_time."','".$mac_result."','".$cuda_result."','".$opencl_result."')";
            $queryobj->exec($sql);
            
            // Send result
            $out = array("status"=>1);
            $obj->view('json', array('msg' => $out));
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
        $obj = new View();
        // Authenticate
        if (! $this->authorized()) {
            $obj->view('json', array('msg' => array('error' => 'Not authenticated')));
            return;
        }

        // Check if we are returning a list of all serials or processing a serial
        // Returns either a list of all serial numbers in MunkiReport OR
        // a JSON of what serial number was just ran with the status of the run
        if ( $incoming_serial == ''){
            // Get all the serial numbers in an object
            $machine = new Machine_model();
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
            $obj->view('json', array('msg' => $out));
        } else {

            // Check if machine is a virutal machine
            $machine = new Machine_model($incoming_serial);
            if (strpos($machine->rs["machine_desc"], 'virtual machine') !== false || strpos($machine->rs["machine_model"], 'VMware') !== false){
                $out = array("serial"=>$incoming_serial,"status"=>"Virtual machine skipped");
                $obj->view('json', array('msg' => $out));
            } else if ($machine->rs["machine_model"] == "" || $machine->rs["machine_model"] == null){
                $out = array("serial"=>$incoming_serial,"status"=>"Skipping machine, does not exist");
                $obj->view('json', array('msg' => $out));
            } else {
                $geekbench = new Geekbench_model($incoming_serial);
                $geekbench_status = $geekbench->process();
                // Check if machine matched
                if ($geekbench->rs["score"] == "" || $geekbench->rs["score"] == null){
                    $out = array("serial"=>$incoming_serial,"status"=>"Machine not matched");
                    $obj->view('json', array('msg' => $out));
                } else {
                    $out = array("serial"=>$incoming_serial,"status"=>"Machine processed");
                    $obj->view('json', array('msg' => $out));
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
        // Authenticate
        if (! $this->authorized()) {
            die('Authenticate first.'); // Todo: return json?
        }
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
        $obj = new View();

        if (! $this->authorized()) {
            $obj->view('json', array('msg' => 'Not authorized'));
            return;
        }

        $geekbench = new Geekbench_model($serial_number);
        $obj->view('json', array('msg' => $geekbench->rs));
    }
} // END class Geekbench_controller
