<?php
include_once "table_common.php";
class table_jjsan_pictext_settings extends table_common
{
    static $_t = 'jjsan_pictext_settings';

	public function __construct() {
		$this->_table = 'jjsan_pictext_settings';
		$this->_pk    = 'id';
		parent::__construct();
	}
	
	public function fetch_by_field($k,$v) {
		return DB::fetch_first('SELECT * FROM %t WHERE %i ', array($this->_table, DB::field($k, $v)));
	}
	
	public function delete_by_field($k,$v) {
		return DB::delete($this->_table, DB::field($k, $v), null, false);
	}
	
	public function fetch_all() {
		return DB::fetch_all('SELECT * FROM %t', array($this->_table));
	}
}
