<?php
include_once "table_common.php";
class table_jjsan_umbrella extends table_common
{
    static $_t = 'jjsan_umbrella';

	public function __construct() {

		$this->_table = 'jjsan_umbrella';
		$this->_pk    = 'id';

		parent::__construct();
	}

    public function fetch_by_field($k,$v) {
        return DB::fetch_first('SELECT * FROM %t WHERE %i ', array($this->_table, DB::field($k, $v)));
    }

    public function updateStatusByUmbrellaId($umId, $status)
    {
        return DB::query('UPDATE %t SET %i WHERE %i', [
            $this->_table,
            DB::field('status', $status),
            DB::field('id', $umId)
        ]);
	}

    public function handleRent($umbrellaId, $orderId)
    {
        $umbrella = $this->fetch($umbrellaId);

        if (!$umbrella) {
            return DB::insert($this->_table, [
                'id' => $umbrellaId,
                'station_id' => 0,
                'order_id' => $orderId,
                'sync_time' => time(),
                'status' => UMBRELLA_OUTSIDE,
            ], true);
        }

        return DB::query('UPDATE %t SET %i, %i, %i, %i, %i WHERE %i', [
            $this->_table,
            DB::field('order_id', $orderId),
            DB::field('station_id', 0),
            DB::field('sync_time', time()),
            DB::field('status', UMBRELLA_OUTSIDE),
            DB::field('slot', 0),
            DB::field('id', $umbrellaId),
        ]);
	}

    public function getLimitedUmbrellas($sid, $limit) {
	    if ($limit == 0) return [];
	    return $this->where(['station_id' => $sid, 'status' => ['value' => UMBRELLA_OUTSIDE, 'glue' => '<>']])
            ->order('sync_time desc')
            ->limit($limit)->get();
	}

    public function getAllStationIdsWithUmbrellaSync() {
        $rst = $this->select('station_id')->where(['status' => UMBRELLA_OUTSIDE_SYNC, 'station_id' => ['value' => 0, 'glue' => '>']])->group('station_id')->get();
        $ids = [];
        $ids = array_map(function($a){
            return $a['station_id'];
        }, $rst);
        return $ids;
    }

}
