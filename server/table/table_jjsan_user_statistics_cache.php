<?php
include_once "table_common.php";
class table_jjsan_user_statistics_cache extends table_common {

	static $_t = "jjsan_user_statistics_cache";

	public function __construct() {
		$this->_table = 'jjsan_user_statistics_cache';
		$this->_pk    = 'id';
		parent::__construct();
	}

    public function set($date ,$platform, $data)
    {
		// 有就更新　$data 数据
		if($cache = $this -> get($date,$platform)){
			$this -> update($cache['id'],$data);
		// 没有就插入
		}else{
			$data['date'] = $date;
			$data['platform'] = $platform;
			$this -> insert($data);
		}
    }

    public function get($date , $platform)
    {
        $sql = "select * from ".DB::table($this->_table)." where date = '{$date}' and platform = '{$platform}'";
        return DB::fetch_first($sql);
    }
}
