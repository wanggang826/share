<?php
include_once "table_common.php";
class table_jjsan_user_role extends table_common
{
    static $_t = 'jjsan_user_role';
	public function __construct() {
		$this->_table = 'jjsan_user_role';
		$this->_pk    = 'id';
		parent::__construct();
	}
}
