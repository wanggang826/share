<?php
include_once "table_common.php";
class table_jjsan_user_location_log extends table_common
{
    static $_t = 'jjsan_user_location_log';

	public function __construct() {

		$this->_table = 'jjsan_user_location_log';
		$this->_pk    = 'id';

		parent::__construct();
	}
}
