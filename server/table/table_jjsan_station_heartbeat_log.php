<?php
include_once "table_common.php";
class table_jjsan_station_heartbeat_log extends table_common
{
    static $_t = 'jjsan_station_heartbeat_log';

	public function __construct() {
		$this->_table = 'jjsan_station_heartbeat_log';
		$this->_pk    = 'id';
		parent::__construct();
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

	public function heartbeat($sid) {
		$this->insert(['station_id' => $sid, 'created_at' => time()]);
	}

	public function count_all_by_search($shop_station_id, $beginTime, $endTime, $sid) {
		$shop_station_cond = $shop_station_id ? DB::field('shop_station_id', $shop_station_id) : 1;
		return DB::result_first('SELECT count(*) FROM %t WHERE %i AND %i AND %i AND %i %i', array(
			$this->_table,
			// DB::field('shop_station_id', $shop_station_id),
			$shop_station_cond,
			DB::field('created_at', $beginTime, '>'),
			DB::field('created_at', $endTime, '<'),
			DB::field('station_id', $sid),
			'ORDER BY '.DB::order('id', 'ASC'),
			));
	}

	public function fetch_all_by_search($shop_station_id, $beginTime, $endTime, $sid, $start = 0, $limit = 0) {
		$shop_station_cond = $shop_station_id ? DB::field('shop_station_id', $shop_station_id) : 1;
		return DB::fetch_all('SELECT * FROM %t WHERE %i AND %i AND %i AND %i %i ' .DB::limit($start, $limit), [
			$this->_table,
			// DB::field('shop_station_id', $shop_station_id),
			$shop_station_cond,
			DB::field('created_at', $beginTime, '>'),
			DB::field('created_at', $endTime, '<'),
			DB::field('station_id', $sid),
			'ORDER BY '.DB::order('id', 'DESC'),
		]);
	}

	public function get_heartbeat_count($sid, $begin_time, $end_time) {
		return  DB::result_first("SELECT count(*) FROM %t WHERE %i AND %i AND %i", [
	        'jjsan_station_heartbeat_log',
	        DB::field('station_id', $sid),
	        DB::field('created_at', $begin_time, '>='),
	        DB::field('created_at', $end_time, '<'),
	    ]);
	}

    public function getLatestHeartbeatTime($sid, $begin_time, $end_time)
    {
        return DB::fetch_first("SELECT max(created_at) as latestheartbeattime FROM %t WHERE %i AND %i AND %i", [
            $this->_table,
            DB::field('station_id', $sid),
            DB::field('created_at', $begin_time, '>='),
            DB::field('created_at', $end_time, '<'),
        ])['latestheartbeattime'];
    }

	public function getBeginAndEndHeartbeatTime($sid, $begin_time, $end_time)
    {
        return DB::fetch_first("SELECT min(created_at) as begin, max(created_at) as end FROM %t WHERE %i AND %i AND %i", [
            $this->_table,
            DB::field('station_id', $sid),
            DB::field('created_at', $begin_time, '>='),
            DB::field('created_at', $end_time, '<'),
        ]);
    }
}
