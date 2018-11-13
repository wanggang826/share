<?php
include_once "table_common.php";
class table_jjsan_refund_log extends table_common
{
    static $_t = 'jjsan_refund_log';

	public function __construct() {

		$this->_table = 'jjsan_refund_log';
		$this->_pk    = 'id';

		parent::__construct();
	}

    public function refund_count($beginTime, $endTime)
    {
        return DB::result_first('SELECT count(*) FROM %t WHERE %i AND %i', [
            $this->_table,
            DB::field('request_time', $beginTime, '>'),
            DB::field('request_time', $endTime, '<='),
        ]);
    }

    /**
     *	获取用户提现记录信息
     *	@param
     */
    public function getUserRefundLists($uid){
        $refundLogs_request = DB::fetch_all('SELECT * from %t where %i AND (%i OR %i) %i', array($this->_table, DB::field('uid', $uid), DB::field('status', REFUND_STATUS_REQUEST), DB::field('refund_time', time()-(48*3600), '>'), 'ORDER BY '.DB::order('request_time', 'DESC')));
        $refundLogs_done = DB::fetch_all('SELECT * from %t where %i AND %i AND %i %i', array($this->_table, DB::field('uid', $uid), DB::field('status', REFUND_STATUS_DONE), DB::field('refund_time', time()-(48*3600), '<'), 'ORDER BY '.DB::order('request_time', 'DESC')));
        $refundLogs = array_merge($refundLogs_request,$refundLogs_done);
        $refundLogs = array_slice($refundLogs, 0,30);
        return $refundLogs;
    }

}
