<?php
include_once "table_common.php";
class table_jjsan_shop_type extends table_common
{
    static $_t = 'jjsan_shop_type';
	public function __construct() {
		$this->_table = 'jjsan_shop_type';
		$this->_pk    = 'id';
		parent::__construct();
	}

}
