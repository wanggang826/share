<?php
include_once "table_common.php";
class table_jjsan_admin_shop extends table_common
{

    static $_t = 'jjsan_admin_shop';

    const STATUS_APPLY = 0; // 申请中
    const STATUS_PASS = 1;

    public static $STATUS = [
        self::STATUS_APPLY => '申请中',
        self::STATUS_PASS  => '通过',
    ];

    public function __construct()
    {
		$this->_table = 'jjsan_admin_shop';
		$this->_pk    = 'id';
		parent::__construct();
	}

    public function changeShopStatus($admin_shop_id, $before, $after)
    {
        $count = $this->where(['id' => $admin_shop_id, 'status' => $before])->count();
        if (empty($count) || count((array) $admin_shop_id) != $count) return false;
        return DB::query('UPDATE %t SET %i WHERE %i AND %i', [
            $this->_table,
            DB::field('status', $after),
            DB::field('id', $admin_shop_id),
            DB::field('status', $before),
        ]);
    }

    public function getAccessShops($admin_id)
    {
        $rst = $this->select('shop_id')->where(['admin_id' => $admin_id, 'status' => self::STATUS_PASS])->get();
        return array_map(function($v){
            return $v['shop_id'];
        }, $rst);
    }
}
