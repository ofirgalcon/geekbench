<?php

/**
 * geekbench_controller class
 *
 * @package munkireport
 * @author AvB
 **/
class geekbench_controller extends Module_controller
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

        $geekbench = new geekbench_model($serial_number);
        $obj->view('json', array('msg' => $geekbench->rs));
    }
} // END class geekbench_controller
