<?php
include_once "table_common.php";
class table_jjsan_user_info extends table_common
{
	static $_t = "jjsan_user_info";

	public function __construct() {
		$this->_table = 'jjsan_user_info';
		$this->_pk    = 'id';
		parent::__construct();
	}

	// 查询
	public function get_by_field($field)
	{
		$sql = "select * from ".DB::table($this->_table);
		if($field[2] == 'like'){
			$sql .= ' where '.$field[0]." like '%".$field[1]."%'";
		}
		return DB::fetch_all($sql);
	}

	public function getField($id, $field) {
		$ret = DB::fetch_first("SELECT `{$field}` FROM %t WHERE %i ", array($this->_table, DB::field('id', $id)));
		return $ret[$field];
	}

    /**
     * 微信用户公众号关注的时候就会有更新用户信息
     * 支付宝生活号不关注也能使用,有可能没有用户信息
     *
     */
    public function getUserInfoFromPlatform($uid, $platform)
    {
        switch ($platform) {
            case PLATFORM_WX :
                $userInfo = $this->fetch($uid);
                break;

            case PLATFORM_ALIPAY :
                $userInfo = $this->fetch($uid);
                if (!$userInfo) {
                    require_once JJSAN_DIR_PATH . 'lib/alipay/AlipayAPI.php';
                    $ret = AlipayAPI::getUserInfo();
                    if(empty($ret)){
                        return NULL;
                    }
                    // 选填项目, 可能没有值
                    // 性别与微信公众号统一 1男2女0未知
                    switch (strtolower($ret->gender)) {
                        case 'm':
                            $sex = 1;
                            break;
                        case 'f':
                            $sex = 2;
                            break;
                        default:
                            $sex = 0;
                    }
                    $userInfo['id'] = $uid; // 主键必须要自定义, 要与user表主键统一
                    $userInfo['openid'] = $ret->user_id;
                    $userInfo['nickname'] = json_encode($ret->nick_name);
                    $userInfo['sex'] = $sex;
                    $userInfo['province'] = $ret->province;
                    $userInfo['city'] = $ret->city;
                    $userInfo['country'] = "中国";
                    $userInfo['headimgurl'] = $ret->avatar;
                    $userInfo['update_time'] = date("Y-m-d H:i:s");
                    // 保存用户信息
                    ct('user_info')->insert($userInfo);
                }
                break;

            default:
                $userInfo = [];
        }
        $userInfo['nickname'] = json_decode($userInfo['nickname'], true);
        return $userInfo;
    }

    /**
     * @param $uid
     * @return array|bool
     */
    public function getUserInfoById($uid){
        $user_info = $this->fetch($uid);
        $user = ct('user')->fetch($uid);

        if ($user && $user_info) {
            $data = [
                "code" => 0,
                "msg" => "成功",
                "data" => [
                    "user_info" => [
                        "id" => $uid,
                        "nickname" => $user_info['nickname'],
                        "headimgurl" => $user_info['headimgurl'],
                        "usablemoney" => $user['usablemoney'],
                        "deposit" => $user['deposit'],
                        "refund" => $user['refund'],
                    ],
                ],
            ];
            return $data;
        }

        return false;
    }

    public function fetch_by_id($id){
        return DB::fetch_first("SELECT * FROM %t WHERE %i ", array($this->_table, DB::field('id', $id)));
    }

    public function getUidByOpenid($openid){
        return DB::result_first('select id from %t where openid = %s',array($this->_table,$openid));
    }

}
