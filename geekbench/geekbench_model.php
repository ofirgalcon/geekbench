<?php

use CFPropertyList\CFPropertyList;

class geekbench_model extends \Model
{

    public function __construct($serial = '')
    {
        parent::__construct('id', 'geekbench'); //primary key, tablename
        $this->rs['id'] = 0;
        $this->rs['serial_number'] = $serial;
        $this->rs['score'] = '';
        $this->rs['multiscore'] = '';
        
        if ($serial) {
            $this->retrieve_record($serial);
        }
        
        $this->serial_number = $serial;
    }

    public function process($data)
    {
        $parser = new CFPropertyList();
        $parser->parse($data);
        $plist = array_change_key_case($parser->toArray(), CASE_LOWER);

        foreach (array('score', 'multiscore') as $item) {
            if (isset($plist[$item])) {
                $this->$item = $plist[$item];
            } else {
                $this->$item = '';
            }
        }

        $this->save();
    }
}
