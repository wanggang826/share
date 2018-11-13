<?php
include_once "table_common.php";
class table_jjsan_menu extends table_common
{

    static $_t = 'jjsan_menu';

    public function __construct()
    {
        $this->_table = 'jjsan_menu';
        $this->_pk = 'id';
        parent::__construct();
    }


}
