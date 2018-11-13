<?php
include_once "table_common.php";
class table_jjsan_admin_role extends table_common
{

    static $_t = 'jjsan_admin_role';

    public function __construct()
    {
		$this->_table = 'jjsan_admin_role';
		$this->_pk    = 'id';
		parent::__construct();
	}


}
