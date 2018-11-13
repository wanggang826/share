<?php
include_once "table_common.php";
class table_jjsan_admin extends table_common
{

    static $_t = 'jjsan_admin';

    const COMPANY_YCB = 1;
    const COMPANY_KEK = 2;
    const COMPANY_HSX = 3;

    public static $company_array = [
        self::COMPANY_YCB => '深圳市街借伞科技有限公司',
        self::COMPANY_KEK => '深圳市卡儿酷软件技术有限公司',
        self::COMPANY_HSX => '深圳市华思旭科技有限公司',
    ];

    public function __construct()
    {
		$this->_table = 'jjsan_admin';
		$this->_pk    = 'id';
		parent::__construct();
	}

	public function changeUserStatus($id, $before, $after)
    {
        // 不支持批量改用户信息
        if (is_array($id)) return false;
        return DB::query('UPDATE %t SET %i WHERE %i AND %i', [
           $this->_table,
            DB::field('status', $after),
            DB::field('id', $id),
            DB::field('status', $before),
        ]);
    }

    // 更新用户的部分信息
    public function updateUserInfo($info)
    {
        // 登录名,邮箱不能重名
        $count =$this->where(['id' => ['value' => [$info['admin_id']], 'glue' => 'notin']])
            ->anyWhere(array(['username' => $info['username']], ['email' => $info['email']]))
            ->count();
        if ($count) return false;
        return $this->update($info['admin_id'], [
            'username' => $info['username'],
            'name' => $info['name'],
            'email' => $info['email'],
            'company' => $info['company'],
        ]);

    }
}
