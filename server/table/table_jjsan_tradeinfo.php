<?php
include_once "table_common.php";
class table_jjsan_tradeinfo extends table_common
{
    static $_t = 'jjsan_tradeinfo';
	public function __construct() {
		$this->_table = 'jjsan_tradeinfo';
		$this->_pk    = 'orderid';
		parent::__construct();
	}

	public function count_by_field($k,$v) {
		return DB::result_first('SELECT COUNT(*) FROM %t WHERE %i ', array($this->_table, DB::field($k, $v)));
	}

	public function fetch_by_field($k,$v) {
		return DB::fetch_first('SELECT * FROM %t WHERE %i ', array($this->_table, DB::field($k, $v)));
	}

	public function getField($orderid, $field) {
		$ret = DB::fetch_first("SELECT `{$field}` FROM %t WHERE %i ", array($this->_table, DB::field($this->_pk, $orderid)));
		return $ret[$field];
	}

}
