<?php
include_once "table_common.php";
class table_jjsan_alipay_bill extends table_common
{
    static $_t = 'jjsan_alipay_bill';
	public function __construct() {
		$this->_table = 'jjsan_alipay_bill';
		$this->_pk    = 'orderid';
		parent::__construct();
	}
}
