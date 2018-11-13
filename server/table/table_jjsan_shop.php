<?php
include_once "table_common.php";
class table_jjsan_shop extends table_common
{
    static $_t = 'jjsan_shop';

	public function __construct() {

		$this->_table = 'jjsan_shop';
		$this->_pk    = 'id';

		parent::__construct();
	}

	public function getField($id, $field) {
		$ret = DB::fetch_first("SELECT `{$field}` FROM %t WHERE %i ", array($this->_table, DB::field($this->_pk, $id)));
		return $ret[$field];
	}

    public function getShopIdsByCities($cities)
    {
        if (empty($cities)) return [];
        $rst = $this->select('id')->where(['city' => $cities])->get();
        return array_map(function($rst){
            return $rst['id'];
        }, $rst);
	}
}
