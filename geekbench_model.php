<?php

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
    public function process()
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
        
         // Get JSON from Geekbench API
        ini_set("allow_url_fopen", 1);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, 'https://browser.geekbench.com/mac-benchmarks.json');
        $result = curl_exec($ch);
        curl_close($ch);

        // Decode JSON
        $benchmarks = json_decode($result);

        // Prepare machine CPU type string for matching
        $machine_cpu = preg_replace("/[^A-Za-z0-9]/", '', explode("@", str_replace(array('(R)','CPU ','(TM)2','(TM)','Core2'), array('','',' 2','','Core 2'), $machine_cpu))[0]);
        
        // Prepare machine description for matching
        $machine_desc = preg_replace("/[^A-Za-z0-9]/", '', str_replace(array(',','Early ','Mid ','Late '), array('','','',''), $machine_desc));

        // Loop through all benchmarks until match is found
        foreach($benchmarks->devices as $benchmark){

            // Prepare benchmark name for matching
            $benchmark_name = preg_replace("/[^A-Za-z0-9]/", '', str_replace(array(', ','Early ','Mid ','Late '), array('','','',''), $benchmark->name));

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

                // Save the data
                $this->save();
                
                // Exit loop because we found a match
                break;
            }
        }
    }
}
