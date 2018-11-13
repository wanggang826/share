<?php
include_once "table_common.php";
class table_jjsan_fee_strategy extends table_common
{
    static $_t = 'jjsan_fee_strategy';

	public function __construct() {
		$this->_table = 'jjsan_fee_strategy';
		$this->_pk    = 'id';
		parent::__construct();
	}

    public function getStrategySettings($id)
    {
        $rst = $this->fetch($id);
        if ($rst) {
            return json_decode($rst['fee'], true);
        }
        $rst = json_decode(C::t('common_setting') -> fetch('jjsan_fee_settings'), 1);
        return $rst;
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
