<?php
include_once "table_common.php";
class table_jjsan_station_log extends table_common
{

    public function __construct() {
        $this->_table = substr(__CLASS__, 6);
        self::$_t = $this->_table;
        $this->_pk    = 'id';

        parent::__construct();
    }

    public function updateLoginCount($id, $cnt)
    {
        return DB::query("UPDATE %t SET login_count = login_count + %i, %i WHERE %i", [
            $this->_table,
            DB::quote($cnt),
            DB::field('updated_at', time()),
            DB::field($this->_pk, $id),
        ]);
    }

    public function updateHeartbeatCount($id, $cnt)
    {
        return DB::query("UPDATE %t SET heartbeat_count = heartbeat_count + %i, online_time = heartbeat_count * 1.5, %i WHERE %i", [
            $this->_table,
            DB::quote($cnt),
            DB::field('updated_at', time()),
            DB::field($this->_pk, $id),
        ]);
    }
}