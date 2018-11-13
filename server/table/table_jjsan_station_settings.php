<?php
include_once "table_common.php";
class table_jjsan_station_settings extends table_common
{

    public static $JJSAN_DEFAULT_SYSTEM_SETTINGS = [
        "ip" => SWOOLE_SERVER_INTERNET_IP,
        "port" => SWOOLE_SERVER_PORT,
        "heartbeat" => STATION_HEARTBEAT,
        "checkupdatedelay"  => STATION_CHECK_UPDATE_DELAY,
    ];

    static $_t = 'jjsan_station_settings';

    const STATUS_NORMAL = 0;
    const STATUS_DELETE = -1;

	public function __construct() {
		$this->_table = 'jjsan_station_settings';
		$this->_pk    = 'id';
		parent::__construct();
	}

	/*
		获取所有的同步配置记录
	*/
	public function all_settings()
	{
		return DB::fetch_all("select * from %t where status != ".self::STATUS_DELETE,[$this->_table]);
	}

	/*
		获取某一个设置策略
	*/
	public function getSetting($strategy_id)
	{
		return parent::fetch($strategy_id,true);
	}

	public function delete_settings($arr)
	{
		foreach($arr as $id){
			parent::update($id,['status'=>self::STATUS_DELETE]);
		}
	}

    public function getUsingSetting($id)
    {
        if ($id == 0) return json_decode(C::t('common_setting') -> fetch('jjsan_system_settings'), 1);;
        $setting = $this->where(['id' => $id, 'status' => self::STATUS_NORMAL])->first();
        if (!$setting) json_decode(C::t('common_setting') -> fetch('jjsan_system_settings'), 1);;
        return json_decode($setting['settings'], true);
	}
}
