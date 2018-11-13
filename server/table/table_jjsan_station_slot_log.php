<?php
include_once "table_common.php";
class table_jjsan_station_slot_log extends table_common
{

	public function __construct() {
		$this->_table = substr(__CLASS__, 6);
		self::$_t = $this->_table;
		$this->_pk    = 'id';

		parent::__construct();
	}

    public function getLastSyncTime($stationId, $slot, $type)
    {
        return $this->where([
            'station_id' => $stationId,
            'slot' => $slot,
            'type' => $type
        ])->order('id desc')->first();
	}

    public function deleteStationSlotLog($stationId, $needCleanLogSlots)
    {
        return DB::query('DELETE FROM %t WHERE %i AND %i', [
            $this->_table,
            DB::field('station_id', $stationId),
            DB::field('slot', $needCleanLogSlots)
        ]);
	}
}
