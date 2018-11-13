<?php
include_once "table_common.php";
class table_jjsan_trade_zhima extends table_common
{
    static $_t = 'jjsan_trade_zhima';
    public function __construct() {
        $this->_table = 'jjsan_trade_zhima';
        $this->_pk    = 'orderid';
        parent::__construct();
    }

    public function getField($orderid, $field) {
        $ret = DB::fetch_first("SELECT `{$field}` FROM %t WHERE %i ", array($this->_table, DB::field($this->_pk, $orderid)));
        return $ret[$field];
    }
}
