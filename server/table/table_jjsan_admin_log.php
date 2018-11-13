<?php
include_once "table_common.php";
class table_jjsan_admin_log extends table_common
{
    static $_t = 'jjsan_admin_log';
	public function __construct() {
		$this->_table = 'jjsan_admin_log';
		$this->_pk    = 'id';
		parent::__construct();
	}

    public function get_all_field($start = 0,$limit = 0,$field = null){
		if(is_array($field)){
			$field = join(',', $field);
		}
		$sql = "select $field from %t limit $start,$limit ";
		return DB::fetch_all($sql,[$this->_table]);
	}

	public function count_by_field($k,$v) {
		return DB::result_first('SELECT COUNT(*) FROM %t WHERE %i ', array($this->_table, DB::field($k, $v)));
	}

	public function fetch_by_field($k,$v) {
		return DB::fetch_first('SELECT * FROM %t WHERE %i ', array($this->_table, DB::field($k, $v)));
	}

	public function fetch_all_by_field($k, $v, $start = 0, $limit = 0) {
		return DB::fetch_all('SELECT * FROM %t WHERE %i '.DB::limit($start, $limit), array($this->_table, DB::field($k, $v)));
	}
}
