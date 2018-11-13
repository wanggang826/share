<?php
include_once "table_common.php";
class table_jjsan_admin_city extends table_common
{

    static $_t = 'jjsan_admin_city';

    const STATUS_PASS = 1;

    public function __construct()
    {
		$this->_table = 'jjsan_admin_city';
		$this->_pk    = 'id';
		parent::__construct();
	}

    public function changeCityStatus($admin_id, $before, $after)
    {
        return DB::query('UPDATE %t SET %i WHERE %i AND %i', [
            $this->_table,
            DB::field('status', $after),
            DB::field('admin_id', $admin_id),
            DB::field('status', $before),
        ]);
	}

    // 用户负责的城市
    public function getAccessCities($admin_id){
        $citys = []; // 用户负责的城市
        $b = $this->select('city')->where(['admin_id' => $admin_id,'status' => self::STATUS_PASS])->first();
        $b = json_decode($b['city']);
        foreach($b as $v){
            $res = explode('/',$v);
            if($res[1]){
                $citys[] = $res[1];
            }
        }
        return $citys;
    }

}
