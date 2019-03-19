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
