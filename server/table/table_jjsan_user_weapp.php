<?php
include_once "table_common.php";

class table_jjsan_user_weapp extends table_common
{
	static $_t = 'jjsan_user_weapp';

	public function __construct()
	{
		$this->_table = 'jjsan_user_weapp';
		$this->_pk    = 'id';
		parent::__construct();
	}

	public function count_by_field($k,$v)
	{
		return DB::result_first('SELECT COUNT(*) FROM %t WHERE %i ', array($this->_table, DB::field($k, $v)));
	}

	public function fetch_by_field($k,$v)
	{
		return DB::fetch_first('SELECT * FROM %t WHERE %i ', array($this->_table, DB::field($k, $v)));
	}

	public function fetch_all_by_field($k, $v, $start = 0, $limit = 0)
	{
		return DB::fetch_all('SELECT * FROM %t WHERE %i '.DB::limit($start, $limit), array($this->_table, DB::field($k, $v)));
	}

	public function getField($id, $field)
	{
		$ret = DB::fetch_first("SELECT `{$field}` FROM %t WHERE %i ", array($this->_table, DB::field($this->_pk, $id)));
		return $ret[$field];
    }
}
