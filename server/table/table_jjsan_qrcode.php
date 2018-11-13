<?php
include_once "table_common.php";
class table_jjsan_qrcode extends table_common
{
    static $_t = 'jjsan_qrcode';

	public function __construct() {
		$this->_table = 'jjsan_qrcode';
		$this->_pk    = 'id';
		parent::__construct();
	}

    public function fetch_by_qrcode($result, $platform = PLATFORM_WX){
        $way = $platform == PLATFORM_WX ? 'wx' : 'alipay';
        return DB::result_first('SELECT id FROM %t WHERE %i',array($this->_table, DB::field($way,$result)));
    }
}
