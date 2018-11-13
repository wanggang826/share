<?php
include_once "table_common.php";
class table_jjsan_user extends table_common
{
	const EVENT_SUBSCRIBE   = 1;    // 用户关注事件
	const EVENT_UNSUBSCRIBE = 2;    // 用户取关事件
	const EVENT_SCAN        = 3;    // 用户扫码事件
	const EVENT_SHOP_PAGE   = 4;    // 用户进入雨伞租赁页面
	const EVENT_SHOP_PAY    = 5;    // 用户在雨伞租赁页面支付
	const EVENT_TOP_UP      = 6;    // 用户充值

    // 成功订单
	public $order_success_status = [
	    ORDER_STATUS_RENT_CONFIRM,
        ORDER_STATUS_RETURN,
        ORDER_STATUS_TIMEOUT_CANT_RETURN,
        ORDER_STATUS_TIMEOUT_NOT_RETURN,
        ORDER_STATUS_LOSS,
        ORDER_STATUS_RETURN_EXCEPTION_SYS_REFUND,
        ORDER_STATUS_RETURN_EXCEPTION_MANUALLY_REFUND
    ];

	public $tag = TAG_UMBRELLA; // 雨伞

	static $_t = 'jjsan_user';

	public function __construct()
	{
		$this->_table = 'jjsan_user';
		$this->_pk    = 'id';
		$this -> order_success_status = join($this->order_success_status,',');
		parent::__construct();
	}

	public function get_all_field($start = 0,$limit = 0,$field = null)
	{
		if(is_array($field)){
			$field = join(',', $field);
		}
		$sql = "select $field from %t limit $start,$limit ";
		return DB::fetch_all($sql,[$this->_table]);
	}

	public function count_by_field($k,$v)
	{
		return DB::result_first('SELECT COUNT(*) FROM %t WHERE %i ', array($this->_table, DB::field($k, $v)));
	}

	public function fetch_by_field($k,$v)
	{
		return DB::fetch_first('SELECT * FROM %t WHERE %i ', array($this->_table, DB::field($k, $v)));
	}

	public function fetch_all_by_field($k, $v, $start = 0, $limit = 0)
	{
		return DB::fetch_all('SELECT * FROM %t WHERE %i '.DB::limit($start, $limit), array($this->_table, DB::field($k, $v)));
	}

	public function getField($id, $field)
	{
		$ret = DB::fetch_first("SELECT `{$field}` FROM %t WHERE %i ", array($this->_table, DB::field('id', $id)));
		return $ret[$field];
    }

    public function getAllByOpenid($openid, $platform)
	{
        $ret = DB::fetch_first("select * from %t where %i",[$this -> _table, DB::field('openid',$openid), DB::field('platform',$platform)]);
		return $ret;
    }

	public function getEventKey($id)
	{
		return DB::result_first('SELECT eventkey FROM %t WHERE %i ', array($this->_table, DB::field('id', $id)));
	}

	public function updateEventKey($id, $eventkey)
	{
		return DB::update($this->_table, array('eventkey'=>$eventkey), DB::field('id', $id));
	}

	public function pay($id, $price)
	{
		return DB::query("UPDATE %t SET usablemoney=usablemoney-" . $price . ",deposit=deposit+" . $price . " WHERE usablemoney>=" . $price . " AND %i", array($this->_table, DB::field('id', $id)));
	}

	public function returnBack($id, $refund, $deposit)
	{
		return DB::query("UPDATE %t SET usablemoney=usablemoney+" . $refund . ",deposit=deposit-" . $deposit . " WHERE deposit>=" . $deposit . " AND %i", array($this->_table, DB::field('id', $id)));
	}

	public function refund($id, $refund, $platform)
	{
		return DB::query("UPDATE %t SET refund=refund-" . $refund . " WHERE refund>=" . $refund . " AND %i", array($this->_table, DB::field('id', $id)));
	}

	public function checkRefund($id, $refund)
	{
		return DB::result_first('SELECT * FROM %t WHERE usablemoney>=' . $refund .' AND %i', array($this->_table, DB::field('id', $id)));
	}

	// 提现处理成功返回数组【退款数量，提现记录id】, 失败返回false
	public function refundRequest($id)
	{
        $userInfo = $this->fetch($id);
        if (!$userInfo) {
            LOG::WARN('id: ' . $id . ' not existed, so refund fail');
            return false;
        }
        if (!$userInfo['usablemoney']) {
            LOG::WARN('id: ' . $id . ' useable money: ' . $userInfo['usablemoney']);
            return false;
        }

        try {
            // 增加事务处理
            DB::query('begin');
            $newId = ct('refund_log')->insert([
                'uid'=>$id,
                'refund'=>$userInfo['usablemoney'],
                'status'=>REFUND_STATUS_REQUEST,
                'request_time'=>time()
            ], true);
            $ret = DB::query('UPDATE %t SET refund=refund+' . $userInfo['usablemoney'] . ', usablemoney=usablemoney-' . $userInfo['usablemoney'] . ' WHERE usablemoney>=' . $userInfo['usablemoney'] . ' AND %i', array($this->_table, DB::field('id', $id)));

            if ($newId && $ret) {
                DB::query('commit');
                return ['refund_log_id' => $newId, 'refund_money' => $userInfo['usablemoney']];
            } else {
                DB::query('rollback');
                LOG::INFO('rollback, uid: ' . $id);
                LOG::WARN('refund log new id: ' . $newId);
                LOG::WARN('update user account info result: ' . $ret);
                return false;
            }

        } catch (Exception $e) {
            LOG::ERROR('update user refund failed, roll back id:' . $newId);
            if(! ct('refund_log')->delete($newId)) {
                LOG::ERROR('roll back refund log error, id:' . $newId);
            }
            LOG::WARN('exception: refund request fail, error msg: ' . $e->getMessage());
            return false;
        }
	}

	public function getTotalRefund()
	{
		return DB::result_first('SELECT SUM(refund) FROM %t', array($this->_table));
	}

	public function getUsersRequestRefund()
	{
		return DB::fetch_all('SELECT * FROM %t WHERE refund>0', array($this->_table));
	}

	public function updateByOpenID($openid, $data)
	{
		return DB::update($this->_table, $data, DB::field('openid', $openid));
	}

	public function getChildrenAgent($id)
	{
		return DB::fetch_all('SELECT openid FROM %t WHERE %i', array($this->_table, DB::field('up', $id)));
	}

	public function getDiscuzUID($id)
	{
		return DB::result_first('SELECT uid FROM %t WHERE %i', array($this->_table, DB::field('id', $id)));
	}

	public function getCredits($discuzUid)
	{
		return DB::result_first('SELECT credits FROM %t WHERE %i', array('common_member', DB::field('uid', $discuzUid)));
	}

	public function addDeposit($openid, $deposit)
	{
		return DB::query("UPDATE %t SET deposit=deposit+" . $deposit . " WHERE %i", array($this->_table, DB::field('openid', $openid)));
	}

	public function reduceDeposit($id, $deposit)
	{
		return DB::query("UPDATE %t SET deposit=deposit-" . $deposit . " WHERE %i AND %i", array($this->_table, DB::field('id', $id), DB::field('deposit', $deposit, '>=')));
	}

	public function payMore($id, $payWithUsableMoney, $deposit)
	{
		return DB::query("UPDATE %t SET usablemoney=usablemoney-" . $payWithUsableMoney . ",deposit=deposit+" . $deposit . " WHERE usablemoney>=" . $payWithUsableMoney . " AND %i", array($this->_table, DB::field('id', $id)));
	}

	public function getUID($openid, $platform)
	{
		return DB::result_first('SELECT id FROM %t WHERE %i AND %i', array($this->_table, DB::field('openid', $openid), DB::field('platform', $platform)));
	}

	public function getPlatformFromUid($id)
	{
		return DB::result_first('SELECT platform FROM %t WHERE %i', array($this->_table, DB::field('id', $id)));
	}

	public function getPlatformInfo($id)
	{
		return DB::fetch_first('SELECT platform, openid FROM %t WHERE %i', array($this->_table, DB::field('id', $id)));
	}

	/**
	 * 兼容之前没有使用uid的情况
	 * @param $id 用户表id
	 * @param $openid
	 * @return NULL|['id', 'openid', 'platform']
	 */
	public function getPlatformInfoCompatible($id, $openid)
	{
		if($id == 0) {
			$id = $this->getUID($openid, PLATFORM_WX);
			if(empty($id))
				return NULL;
			return ['id'=>$id, 'openid'=>$openid, 'platform'=>PLATFORM_WX];
		} else {
			$info = $this->getPlatformInfo($id);
			if(empty($info))
				return NULL;
			$info['id'] = $id;
			return $info;
		}
	}

	public function getUserInfoFromPlatform($uid, $openid, $platform)
	{
		if($platform == PLATFORM_ALIPAY) {
            // get user from db
            $sql = "select * from %t where id = %d";
            $users = DB::fetch_first($sql,['jjsan_user_info', $uid]);
            if($users){
                return $users;
            // if we can not get user from db ,get user from alipay ,and set it user
            }else{
				require_once JJSAN_DIR_PATH . 'lib/alipay/AlipayAPI.php';
				$ret = AlipayAPI::getUserInfo();
				if(empty($ret)){
					return NULL;
	            }
				$userInfo['id'] = $uid;
				$userInfo['openid'] = $openid;
				$userInfo['nickname'] = $ret->nick_name;
				$userInfo['sex'] = $ret->gender == 'm'? 1 : 0;
				$userInfo['province'] = $ret->province;
				$userInfo['country'] = "中国";
				$userInfo['headimgurl'] = $ret->avatar;
				$userInfo['update_time'] = date("Y-m-d H:i:s");
				// 保存用户信息
				ct('user_info')->insert($userInfo, false, true);
				return $userInfo;
            }
        } else {
            $sql = "select * from %t where id = %d";
            $users = DB::fetch_first($sql,['jjsan_user_info', $uid]);
			if($users){
				$users['nickname'] = json_decode($users['nickname']);	// for emoji has been encode
				return $users;
			}else{
				$w_userinfo = callWeiXinFuncV2("wxAPI::getUserInfo",[$openid]);
				$userInfo['id'] = $uid;
				$userInfo['openid'] = $openid;
				$userInfo['nickname'] = json_encode($w_userinfo['nickname']);
				$userInfo['sex'] = $w_userinfo['sex'];
				$userInfo['province'] = $w_userinfo['province'];
				$userInfo['country'] = $w_userinfo['country'];
				$userInfo['headimgurl'] = $w_userinfo['headimgurl'];
				$userInfo['update_time'] = date("Y-m-d H:i:s");
				ct('user_info')->insert($userInfo, false, true);
				return $w_userinfo;
			}
		}
	}

	public function user_all_info_list($page,$page_size)
	{
		$page = ($page - 1) * $page_size;
		// select a.*,b.* from ycb_jjsan_user as a left join ycb_jjsan_user_info as b on a.id = b.id order by a.id desc limit 0,10;
		$sql = "select a.*,b.nickname,b.sex,b.province,b.country,b.headimgurl,b.update_time from %t as a left join %t as b on a.id = b.id order by a.create_time desc limit %d,%d";
		$users = DB::fetch_all($sql,['jjsan_user','jjsan_user_info',$page,$page_size]);
		return $users;
	}

	// todo : $page,$page_size
	public function get_between_update_time($stime,$etime)
	{
		// SELECT a.*,b.* from ycb_jjsan_user as a LEFT join ycb_jjsan_user_info as b on a.id = b.id where '2016-1-1' <= date(b.update_time) and date(b.update_time) <= "2016-10-10" limit 1000;
		$sql = "select a.*,b.* from %t as a left join %t as b on a.id = b.id where %s <= date(b.update_time) and date(b.update_time) <= %s order by a.id desc limit 1000";
		$users = DB::fetch_all($sql,['jjsan_user','jjsan_user_info',$stime,$etime]);
		return $users;
	}

    // todo : $page,$page_size
	public function get_before_update_time($etime)
	{
		$sql = "select a.*,b.* from %t as a left join %t as b on a.id = b.id where date(b.update_time) <= %s order by a.id desc limit 1000";
		$users = DB::fetch_all($sql,['jjsan_user','jjsan_user_info',$etime]);
		return $users;
	}

	// todo : $page,$page_size
	public function get_after_update_time($stime)
	{
		$sql = "select a.*,b.* from %t as a left join %t as b on a.id = b.id where %s <= date(b.update_time) order by a.id desc limit 1000";
		$users = DB::fetch_all($sql,['jjsan_user','jjsan_user_info',$stime]);
		return $users;
	}

	public function get_unreturned_order()
	{
		$sql = "select uid,count(uid) as order_num from %t where status =2 GROUP by uid";
		$unreturned_users = DB::fetch_all($sql,['jjsan_tradelog']);
		$user_str = "";
		foreach ($unreturned_users as $v) {
			$user_str[] = $v['uid'];
		}
		$user_str = join($user_str,',');
		$sql = "select a.*,b.* from %t as a left join %t as b on a.id = b.id where a.id in (%s) order by a.id desc limit 1000";
		$users = DB::fetch_all($sql,['jjsan_user','jjsan_user_info',$user_str]);
		return $users;
	}

	// 获取某平台下的用户
	public function get_user_by_platform($platform)
	{
		$sql = "select a.*,b.* from %t as a left join %t as b on a.id = b.id where a.platform = %d order by a.id desc limit 1000";
		$users = DB::fetch_all($sql,['jjsan_user','jjsan_user_info',$platform]);
		return $users;
	}

	// 按条件查询用户列表
	public function search($openid = '',$nickname = '',$platform = '',$status = '',$stime = '',$etime = '',$page = 1,$page_size = 10,$role_selected,$id = '')
	{
		$page = ($page - 1) * $page_size;
		$where = '';
		$condition = [];

		$condition[] = " 1 = 1 ";

		// platform
		if($platform == 1 || $platform == 0){
			$condition[] = "a.platform = $platform";
		}

		// role_selected
		if($role_selected >= 0){
				$condition[] = "a.role_id = $role_selected";
		}

		// status
		$sql = "select uid,count(uid) as order_num from %t where status =2 GROUP by uid";
		$unreturned_users = DB::fetch_all($sql,['jjsan_tradelog']);
		$user_str = "";
		foreach ($unreturned_users as $v) {
			$user_str[] = $v['uid'];
		}
		$user_str = join($user_str,',');
		// 有借出
		if($user_str){
			// 求借出用户　
			if($status == 1){
				$condition[] = "a.id in ($user_str)";
			// 求未借出用户
			}elseif($status == 2){
				$condition[] = "a.id not in ($user_str)";
			}
		// 无借出
		}else{
			// 求借出用户　0
			if($status == 1){
				$condition[] = "1 = 2";
			// 求未借出用户　全部
			}elseif($status == 2){
				$condition[] = "1 = 1";
			}
		}

		// 用户id
		if($id){
			$condition[] = " a.id = $id ";
		}

		// stime
		if($stime){
			$condition[] = "date(a.create_time) >= '$stime'";
		}

		// etime
		if($etime){
			$condition[] = "date(a.create_time) <= '$etime'";
		}

		$params = ['jjsan_user','jjsan_user_info']; // ,'%'.$openid.'%','%'.$nickname.'%'
		// with openid
		if($openid){
			// openid and nickname
			if(!empty($nickname)){
				$nickname = str_replace('\u','.*',trim(json_encode($nickname),'"')); // 临时方案,json_encode 存储的nickname会造成查询上的一些问题
				$like = "a.openid like '%$openid%' and b.nickname like '%$nickname%' ESCAPE '/'";
			// only openid
			}else{
				$like = "a.openid like '%$openid%'";
			}
		// only nickname
		}elseif(!empty($nickname)){
			$nickname = str_replace('\u','.*',trim(json_encode($nickname),'"')); // 临时方案
			$like = "b.nickname regexp '$nickname'";
		// without nickname and openid
		}else{
			$like = "1 = 1";
		}

		$where .= join($condition,' and ');

		$sql_for_users  = "select a.*,b.nickname,b.sex,b.province,b.country,b.headimgurl,b.update_time";
		$sql_for_users .= " from ".DB::table('jjsan_user')." as a left join ".DB::table('jjsan_user_info')." as b on a.id = b.id ";
		$sql_for_users .= " where $where and $like order by a.create_time desc limit $page,$page_size";
		$sql_for_count  = "select count(a.id) as count from ".DB::table('jjsan_user')." as a left join ".DB::table('jjsan_user_info')." as b on a.id = b.id where $where and $like";

		$users = DB::fetch_all($sql_for_users);
		$count = DB::fetch_all($sql_for_count)[0]['count'];

		foreach($users as &$user){
			if(json_decode($user['nickname'])){
				$user['nickname'] = json_decode($user['nickname']);
			}
		}

		return ['data'=>$users,'count'=>$count];
	}

	// 统计日志事件列表
	public function user_log_count($date,$platform = 2 )
	{
		$sql = "select a.type,count(a.uid) as num from ".DB::table('jjsan_user_log')." as a inner join ".DB::table('jjsan_user')." as b on a.uid = b.id ";
		$sql .= " where DATE_FORMAT(a.create_time,'%Y-%m-%d') = '$date' ";
		if($platform != 2){
			$sql .= " and b.platform = $platform ";
		}
		$sql .= " group by a.type ";
		return DB::fetch_all($sql);
	}

	// 统计日志用户列表
	public function user_log_user_count($date,$platform = 2,$u = '')
	{
		$sql = "select a.type,COUNT(DISTINCT a.uid) as num from ".DB::table('jjsan_user_log')." as a inner join ".DB::table('jjsan_user')." as b on a.uid = b.id ";
		$sql .= " where DATE_FORMAT(a.create_time,'%Y-%m-%d') = '$date' ";
		if($platform !=2){
			$sql .= " and platform = $platform ";
		}
		if($u == 'new'){
			$sql = "select a.type,count(distinct a.uid) as num ";
			$sql .= "from ".DB::table('jjsan_user_log')." as a inner join ".DB::table('jjsan_user_log')." as b on DATE_FORMAT(b.create_time,'%Y-%m-%d') = '{$date}' and b.type = 1 and  a.uid = b.uid and DATE_FORMAT(a.create_time,'%Y-%m-%d') = '{$date}'";
			$sql .= "left join ".DB::table('jjsan_user')." as c on a.uid = c.id ";
			if($platform !=2){
				$sql .= " where c.platform = $platform ";
			}
		}
		$sql .= " GROUP by a.type ";
		return DB::fetch_all($sql);
	}

	// 统计日志用户累积列表
	public function user_log_user_accumulated($date,$platform = 2,$u = "ALL")
	{
		$sql = "select a.type,COUNT(DISTINCT a.uid) as num from ".DB::table('jjsan_user_log')." as a inner join ".DB::table('jjsan_user')." as b on a.uid = b.id ";
		$sql .= " where DATE_FORMAT(a.create_time,'%Y-%m-%d') <= '$date' ";
		if($platform !=2){
			$sql .= " and platform = $platform ";
		}
		if($u != 'ALL'){
			if(count($u) == 0){
				return [];
			}
			$str = join($u,',');
			$sql .= " and a.uid in ($str) ";
		}
		$sql .= " GROUP by a.type ";
		return DB::fetch_all($sql);
	}


	// 某日期下支付页面进入数
	public function shop_page_num($date,$u = 'ALL')
	{
		$sql = "SELECT COUNT(*) as num from ".DB::table('jjsan_user_log')." where type = ".self::EVENT_SHOP_PAGE." and DATE_FORMAT(create_time,'%Y-%m-%d') = '{$date}'";
		if($u == 'ALL'){
			// do nothing
		}else{
			if(is_array($u) && !empty($u)){
				$u = join($u,',');
				$sql .= " and uid in ($u)";
			}else{
				return 0;
			}
		}
		return DB::fetch_first($sql)['num'];
	}

	// 某日期下进页面人数
	public function shop_page_user_count($date)
	{
		$sql = "SELECT COUNT(distinct uid) as num from ".DB::table('jjsan_user_log')." where type = ".self::EVENT_SHOP_PAGE." and DATE_FORMAT(create_time,'%Y-%m-%d') = '{$date}'";
		return DB::fetch_first($sql)['num'];
	}

	// 某日期下支付按钮点击数
	public function pay_button_num($date,$u = "ALL")
	{
		$sql = "SELECT COUNT(*) as num from ".DB::table('jjsan_user_log')." where type = ".self::EVENT_SHOP_PAY." and DATE_FORMAT(create_time,'%Y-%m-%d') = '{$date}'";
		if($u == 'ALL'){
			// do nothing
		}else{
			if(is_array($u) && !empty($u)){
				$u = join($u,',');
				$sql .= " and uid in ($u)";
			}else{
				return 0;
			}
		}
		return DB::fetch_first($sql)['num'];
	}

	// 某日期下点击借操作用户数
	public function pay_button_user_count($date,$u = "ALL")
	{
		$sql = "SELECT COUNT(distinct uid) as num from ".DB::table('jjsan_user_log')." where type = ".self::EVENT_SHOP_PAY." and DATE_FORMAT(create_time,'%Y-%m-%d') = '{$date}'";
		return DB::fetch_first($sql)['num'];
	}

	// 某日期下用户关注数
	public function user_subscribe_num($date,$u = "ALL")
	{
		$sql = "SELECT COUNT(*) as num from ".DB::table('jjsan_user_log')." where type = ".self::EVENT_SUBSCRIBE." and DATE_FORMAT(create_time,'%Y-%m-%d') = '{$date}'";
		if($u == 'ALL'){
			// do nothing
		}else{
			if(is_array($u) && !empty($u)){
				$u = join($u,',');
				$sql .= " and uid in ($u)";
			}else{
				return 0;
			}
		}
		return DB::fetch_first($sql)['num'];
	}

	// 某日期用户取关事件数
	public function user_unsubscribe_num($date,$u = 'ALL')
	{
		$sql = "SELECT COUNT(*) as num from ".DB::table('jjsan_user_log')." where type = ".self::EVENT_UNSUBSCRIBE." and DATE_FORMAT(create_time,'%Y-%m-%d') = '{$date}'";
		if($u == 'ALL'){
			// do nothing
		}else{
			if(is_array($u) && !empty($u)){
				$u = join($u,',');
				$sql .= " and uid in ($u)";
			}else{
				return 0;
			}
		}
		return DB::fetch_first($sql)['num'];
	}

	// 某日期下用户扫码数
	public function user_scan_num($date,$u = "ALL")
	{
		$sql = "SELECT COUNT(*) as num from ".DB::table('jjsan_user_log')." where type = ".self::EVENT_SCAN." and DATE_FORMAT(create_time,'%Y-%m-%d') = '{$date}'";
		if($u == 'ALL'){
			// do nothing
		}else{
			if(is_array($u) && !empty($u)){
				$u = join($u,',');
				$sql .= " and uid in ($u)";
			}else{
				return 0;
			}
		}
		return DB::fetch_first($sql)['num'];
	}


	// 充值成功用户
	public function user_up_top($date,$u = 'ALL')
	{
		$sql = "SELECT distinct uid from ".DB::table('jjsan_user_log')." where type = ".self::EVENT_TOP_UP." and DATE_FORMAT(create_time,'%Y-%m-%d') = '{$date}' and uid > 0";
		if($u == 'ALL'){
			// do nothing
		}else{
			if(is_array($u) && !empty($u)){
				$u = join($u,',');
				$sql .= " and uid in ($u)";
			}else{
				return [];
			}
		}
		// echo $sql."<br>";
		$up_top_user =  DB::fetch_all($sql);
		$up_top_user_ids = [];
		foreach ($up_top_user as $u) {
			$up_top_user_ids[] =  $u['uid'];
		}
		return $up_top_user_ids;
	}

	// 某日期下充值成功用户数
	public function user_up_top_count($date,$u = "ALL")
	{
		$sql = "SELECT count(distinct uid) as num from ".DB::table('jjsan_user_log')." where type = ".self::EVENT_TOP_UP." and DATE_FORMAT(create_time,'%Y-%m-%d') = '{$date}' and uid > 0";
		if($u == 'ALL'){
			// do nothing
		}else{
			if(is_array($u) && !empty($u)){
				$u = join($u,',');
				$sql .= " and uid in ($u)";
			}
		}
		return DB::fetch_first($sql)['num'];
	}

	// 某日期下充值成功 累计人数
	public function up_top_user_accumulated($date,$u = "ALL")
	{
		$sql = "SELECT count(distinct uid) as num from ".DB::table('jjsan_user_log')." where type = ".self::EVENT_TOP_UP." and DATE_FORMAT(create_time,'%Y-%m-%d') <= '{$date}' and uid > 0";
		if($u == 'ALL'){
			// do nothing
		}else{
			if(is_array($u) && !empty($u)){
				$u = join($u,',');
				$sql .= " and uid in ($u)";
			}
		}
		return DB::fetch_first($sql)['num'];
	}

	// 累计充值用户
	public function user_up_top_accumulated($date)
	{
		$sql = "SELECT count(*) as num from ".DB::table('jjsan_user_log')." where type = ".self::EVENT_TOP_UP." and DATE_FORMAT(create_time,'%Y-%m-%d') <= '{$date}' and uid > 0";
		return DB::fetch_first($sql)['num'];
	}

	// 某日期下的新用户,就是进行了关注的用户
	public function new_user($date)
	{
		$sql = "select distinct uid from ".DB::table("jjsan_user_log")." where type = ".self::EVENT_SUBSCRIBE." and DATE_FORMAT(create_time,'%Y-%m-%d') = '{$date}' and uid > 0";
		$user = DB::fetch_all($sql);
		$new_user = [];
		foreach ($user as $n) {
			$new_user[] = $n['uid'];
		}
		return $new_user;
	}

	// 截止到某日期下的用户数
	public function user_count($date = "ALL")
	{
		$sql = "select count(*) as num from ".DB::table("jjsan_user")." where DATE_FORMAT(create_time,'%Y-%m-%d') <= '{$date}'";
		return DB::fetch_first($sql)['num'];
	}

	// 某日期下的订单数
	public function success_order_count($date)
	{
		$sql = "select count(*) as num from ".DB::table('jjsan_tradelog')." where FROM_UNIXTIME(borrow_time,'%Y-%m-%d') = '$date' and status in (2,3)";
		return DB::fetch_first($sql)['num'];
	}

	// 某日期下的付费订单数
	public function success_order_fee_count($date)
	{
		$sql = "select count(*) as num from ".DB::table('jjsan_tradelog')." where FROM_UNIXTIME(borrow_time,'%Y-%m-%d') = '$date' and status in (2,3) and usefee > 0";
		return DB::fetch_first($sql)['num'];
	}

    // 某日期下租借成功的新用户订单总数
    public function success_user_order_count($date,$platform,$u)
    {
        if($u == 'new'){
            // SELECT count(DISTINCT b.orderid) from ycb_jjsan_user_log as a inner join ycb_jjsan_tradelog as b
            // on a.uid = b.uid and date_format(a.create_time,'%Y-%m-%d') = '2017-01-05' and from_unixtime(b.borrow_time,'%Y-%m-%d') = '2017-01-05' and a.type = 1 and b.status in (2,3,6,98) and b.tag = 111
            // where b.platform in (1,2)
            $sql = "select count(distinct b.orderid) as num from ".DB::table('jjsan_user_log')." as a inner join ".DB::table('jjsan_tradelog')." as b on a.uid = b.uid and date_format(a.create_time,'%Y-%m-%d') = '{$date}' and from_unixtime(b.borrow_time,'%Y-%m-%d') = '{$date}' and a.type = 1 and b.status in ({$this -> order_success_status}) and b.tag = {$this->tag} ";
            if($platform == 0){
                $sql .= " and b.platform in (0) ";
            }
            if($platform == 1){
                $sql .= " and b.platform in (1,2) ";
            }
            return DB::fetch_first($sql)['num'];
        }

    }

	// 某日期下租借成功的用户总数
	public function success_order_user_count($date,$platform = 2,$u = "",$is_fee = false)
	{
		$sql = "select count(distinct uid) as num from ".DB::table('jjsan_tradelog');
		$sql .= " where  tag like '{$this->tag}' ".$this->_order_platform($platform);
		// 是否付费订单
		if($is_fee){
			$sql .= " and usefee > 0 and FROM_UNIXTIME(return_time, '%Y-%m-%d') = '$date' ";
		}else{
			$sql .= " and status in ({$this->order_success_status}) and FROM_UNIXTIME(borrow_time, '%Y-%m-%d') = '$date' ";
		}
		// 如果是求新用户　租借成功用户总数
		if($u == 'new'){
			$sql = "select count(distinct a.uid) as num from ".DB::table('jjsan_tradelog')." as a inner join ".DB::table('jjsan_user_log')." as b on a.uid = b.uid ";
			$sql .= " inner join ".DB::table('jjsan_user')." as c on b.uid = c.id ";
			$sql .= " where a.tag like '{$this->tag}' and b.type = 1 and '{$date}' = DATE_FORMAT(b.create_time,'%Y-%m-%d') and from_unixtime(a.borrow_time,'%Y-%m-%d') = '{$date}' and a.status in ({$this -> order_success_status})";
			if($platform != 2){
				$sql .= " and c.platform = $platform";
			}
			// 是否付费订单
			if($is_fee){
				$sql .= " and a.usefee > 0 ";
			}else{
				$sql .= " and a.status in ({$this->order_success_status}) ";
			}
		}
		// 如果是求老用户租借成功用户总数
		// 逻辑是 租借成功老用户数 = 租借成功总用户数 - 租借成功新用户数 + 当天先取消关注而后再关注的租借成功用户数
		// 原因是 老用户取消关注 再关注 也算新用户，同时也是老用户
		if($u == 'old'){

			// 租借成功总用户数
			$sql = "select count(distinct uid) as num from ".DB::table('jjsan_tradelog');
			$sql .= " where  tag like '{$this->tag}' ".$this->_order_platform($platform);
			if($is_fee){
				$sql .= " and usefee > 0 and FROM_UNIXTIME(return_time, '%Y-%m-%d') = '$date' ";
			}else{
				$sql .= " and status in ({$this->order_success_status}) and FROM_UNIXTIME(borrow_time, '%Y-%m-%d') = '$date' ";
			}
			$total_user = DB::fetch_first($sql)['num'];

			// 租借成功新用户数
			$sql = "select count(distinct a.uid) as num from ".DB::table('jjsan_tradelog')." as a inner join ".DB::table('jjsan_user_log')." as b on a.uid = b.uid ";
			$sql .= " inner join ".DB::table('jjsan_user')." as c on b.uid = c.id ";
			$sql .= " where a.tag like '{$this->tag}' and b.type = 1 and '{$date}' = DATE_FORMAT(b.create_time,'%Y-%m-%d') and from_unixtime(a.borrow_time,'%Y-%m-%d') = '{$date}' and a.status in ({$this -> order_success_status})";
			if($platform != 2){
				$sql .= " and c.platform = $platform";
			}
			if($is_fee){
				$sql .= " and a.usefee > 0 ";
			}else{
				$sql .= " and a.status in ({$this->order_success_status}) ";
			}
			$new_user = DB::fetch_first($sql)['num'];

			// 当天先取消而后再关注的租借成功的用户数
			$u_user_count = 0;
			$sql = "SELECT distinct a.uid as uid from ".DB::table('jjsan_user_log')." as a inner join ".DB::table('jjsan_user_log')." as b on a.uid = b.uid ";
			$sql .= "where date_format(a.create_time,'%Y-%m-%d') = '{$date}' and date_format(b.create_time,'%Y-%m-%d') = '$date' and a.type = 1 and b.type = 2 and a.create_time > b.create_time";
			$u_user = DB::fetch_all($sql); // 所有有 取关在关注之前记录 的用户
			if($u_user){
				$temp_user = [];
				foreach($u_user as $v){
					$temp_user[] = $v['uid'];
				}
				$temp_user = join(',',$temp_user);
				$sql = "select uid,min(create_time) as time from ".DB::table('jjsan_user_log')." where date_format(create_time,'%Y-%m-%d') = '{$date}' and type in (1,2) and uid in ($temp_user) GROUP by uid";
				$first_sub_unsub_user = DB::fetch_all($sql); // 用户第一次取关或者关注的记录

				$first_unsubscribe_user = false; // 取关后再关注的老用户
				foreach($first_sub_unsub_user as $f){
					$sql = "select uid from ".DB::table('jjsan_user_log')." where type = 2 and create_time = '{$f[time]}' and uid = '{$f[uid]}'"; // 第一次为取关的用户，这些用户就是取关再关注的用户
					$temp_unsubscribe_user = DB::fetch_first($sql)['uid'];
					if($temp_unsubscribe_user){
						$first_unsubscribe_user[] = $temp_unsubscribe_user;
					}
				}
				if($first_unsubscribe_user){
					$user_str = join(',',$first_unsubscribe_user);
					$sql = "select count(distinct uid) as num from ".DB::table("jjsan_tradelog")." where uid in ($user_str) and tag in ({$this -> tag}) and from_unixtime(borrow_time,'%Y-%m-%d') = '{$date}' ";
					if($platform == 0){
						$sql .= " and platform in (0) ";
					}
					if($platform == 1){
						$sql .= " and platform in (1,2) ";
					}
					if($is_fee){
						$sql .= " and usefee > 0 ";
					}else{
						$sql .= " and status in ({$this->order_success_status}) ";
					}
					$u_user_count = DB::fetch_first($sql)['num'];
				}
			}
			return ($total_user - $new_user + $u_user_count);
		}
		return DB::fetch_first($sql)['num'];
	}

	// 某段时间　租借成功人数
	public function success_user_count_by_date($stime,$etime,$platform = 2,$is_fee = false)
	{
		$sql = "select count(distinct uid) as num from ".DB::table('jjsan_tradelog');
		$sql .= " where borrow_time < '{$etime}' and borrow_time > '{$stime}' and tag like '{$this->tag}' ".$this->_order_platform($platform);
		// 是否付费订单
		if($is_fee){
			$sql .= " and usefee > 0 ";
		}else{
			$sql .= " and status in ({$this->order_success_status}) ";
		}
		return DB::fetch_first($sql)['num'];
	}

	// 选择平台
	public function _order_platform($platform = 2)
	{
		if($platform == 1){
			return " and platform in (1,2) ";
		}elseif($platform == 0){
			return " and platform in (0) ";
		}else{
			return '';
		}
	}

	// 某日期下累计用户数
	public function user_accumulated($date,$platform = 2)
	{
		$sql = "select count(*) as num from ".DB::table('jjsan_user')." where '{$date}' >= DATE_FORMAT(create_time,'%Y-%m-%d') and unsubscribe = 0";
		if($platform != 2){
			$sql .= " and platform = $platform ";
		}
		return DB::fetch_first($sql)['num'];
	}

	// 某日期下租借成功的用户总数 付费
	public function success_order_fee_user_count($date,$platform = 2,$u = 'ALL')
	{
		$sql = "select count(distinct uid) as num from ".DB::table('jjsan_tradelog');
		$sql .= " where FROM_UNIXTIME(borrow_time,'%Y-%m-%d') = '$date' and usefee > 0".$this -> _order_platform($platform);
		if($platform != 2){
			$sql .= " and platform = $platform";
		}
		if($u != 'ALL'){
			if(count($u) == 0){
				return 0;
			}
			$str = join($u,',');
			$sql .= " and uid in ($str) ";
		}
		return DB::fetch_first($sql)['num'];
	}



	// 某日期下租借成功且付费的用户
	public function success_fee_order_user($date,$u = 'ALL')
	{
		$sql = "select distinct uid from ".DB::table('jjsan_tradelog')." where FROM_UNIXTIME(borrow_time, '%Y-%m-%d') = '$date' and status in (2,3) and usefee > 0";
		if($u == "ALL"){
			// do nothing
		}else{
			if(is_array($u) && !empty($u)){
				$u = join($u,',');
				$sql .= " and uid in ($u)";
			}else{
				return [];
			}
		}
		$success_user = DB::fetch_all($sql);
		$su = [];
		foreach ($success_user as $user) {
			$su[] = $user['uid'];
		}
		return $su;
	}

	// 到某日期下 累计付费的用户
	public function success_fee_order_user_accumulated($date,$platform = 2,$u = "ALL")
	{
		$sql = "select count(distinct uid) as num from ".DB::table('jjsan_tradelog');
		$sql .= " where FROM_UNIXTIME(borrow_time, '%Y-%m-%d') <= '$date' and usefee > 0 and tag = {$this->tag}".$this -> _order_platform($platform);
		if($u != 'ALL'){
			if(count($u) == 0){
				return 0;
			}
			$str = join($u,',');
			$sql .= " and uid in ($str) ";
		}
		return DB::fetch_first($sql)['num'];
	}

	// 某日期新增用户数
	public function user_increase_count($date)
	{
		$sql = "select count(*) as num from ".DB::table('jjsan_user')." where DATE_FORMAT(create_time,'%Y-%m-%d') = '{$date}' and (openid != '' or openid is not null)";
		return DB::fetch_first($sql)['num'];
	}

	// 某日期净增用户
	public function user_increase($date)
	{
		// 今日新增用户
		$sql  = "select id from ".DB::table('jjsan_user')." where DATE_FORMAT(create_time,'%Y-%m-%d') = '{$date}' and openid != ''";
		$user = DB::fetch_all($sql);
		if($user){
			$user_return = [];
			foreach ($user as $x) {
				$user_return[] = $x['id'];
			}
			return $user_return;
		}else {
			return [];
		}
	}

	// 查当天退款的次数 , $platform 用于区分平台
	public function refund_count($date , $platform = 2)
	{
		$sql = "SELECT COUNT(a.uid) as num from ".DB::table('jjsan_refund_log')." as a INNER join ".DB::table('jjsan_user');
		$sql .= " as b on a.uid = b.id where '{$date}' = FROM_UNIXTIME(a.request_time,'%Y-%m-%d') ";
		if($platform != 2){
			$sql .= " and b.platform = $platform ";
		}
		return DB::fetch_first($sql)['num'];
	}

	// 借出次数
	public function order_count($date , $platform = 2)
	{
		$sql = "select count(*) as num from ".DB::table('jjsan_tradelog');
		$sql .= " where FROM_UNIXTIME(borrow_time, '%Y-%m-%d') = '$date' and status in ({$this->order_success_status}) and tag = {$this->tag}".$this->_order_platform($platform) ;
		return DB::fetch_first($sql)['num'];
	}

	// 付押金人数　, 大于100 才算押金
	public function top_up_user_count($date,$platform = 2,$u  = 'all')
	{
		if($u == 'all'){
			$sql = 'select count(distinct a.uid) as num from '.DB::table("jjsan_user_log").' as a inner join '.DB::table("jjsan_user").' as b on a.uid = b.id ';
			$sql .= "where date_format(a.create_time,'%Y-%m-%d') = '{$date}'  and a.type = 6 and a.detail regexp '[0-9]{3,}'";
			if($platform != 2){
				$sql .= " and b.platform = $platform";
			}
		}
		if($u == 'new'){
			$sql = "select count(distinct a.uid) as num from " . DB::table('jjsan_user_log') . " as a inner join " . DB::table('jjsan_user_log') . " as b inner join " . DB::table('jjsan_user_log') . " as c on a.uid = b.uid and b.uid = c.id ";
			$sql .= " where date_format(a.create_time,'%Y-%m-%d') = '{$date}' and date_format(b.create_time,'%Y-%m-%d') = '{$date}' and a.type = 6 and b.type = 1 and a.detail regexp '[0-9]{3,}' ";
			if($platform != 2){
				$sql .= " and c.platform = $platform ";
			}
		}

		return DB::fetch_first($sql)['num'];
	}

	// 某段时间内付押金的人数
	public function top_up_user_count_by_date($stime,$etime,$platform = 2)
	{
		// return 'aaa';
		$sql = "select count(distinct a.uid) as num from  ".DB::table('jjsan_user_log')." as a inner join ".DB::table('jjsan_user')." as b on a.uid = b.id ";
		$sql .= " where unix_timestamp(a.create_time) > '$stime' and unix_timestamp(a.create_time) < '$etime' and a.detail regexp '[0-9]{3,}' and a.type = ".self::EVENT_TOP_UP;
		if($platform != 2){
			$sql .= " and b.platform = $platform ";
		}
		return DB::fetch_first($sql)['num'];
	}

	// 付押金次数　, 大于100 才算押金
	public function top_up_success_count($date,$platform = 2,$u = 'all')
	{
		$sql = 'select count(*) as num from '.DB::table("jjsan_user_log").' as a inner join '.DB::table("jjsan_user").' as b on a.uid = b.id ';
		$sql .= "where date_format(a.create_time,'%Y-%m-%d') = '{$date}'  and a.type = 6 and a.detail regexp '[0-9]{3,}'";
		if($platform != 2){
			$sql .= " and b.platform = $platform";
		}
		return DB::fetch_first($sql)['num'];
	}

	// 统计某个区间的　用户进入页面数
	public function shop_page_user_count_by_date($stime,$etime,$platform = 2)
	{
		$sql = "select count(distinct a.uid) as num from ".DB::table('jjsan_user_log')." as a inner join ".DB::table("jjsan_user")." as b on a.uid = b.id ";
		$sql .= " where unix_timestamp(a.create_time) > '$stime' and unix_timestamp(a.create_time) < '$etime' and a.type = ".self::EVENT_SHOP_PAGE;
		if($platform != 2){
			$sql .= " and b.platform = $platform ";
		}
		return DB::fetch_first($sql)['num'];
	}

	// @todo 不管什么时候注册，只要当天产生第一单，就算是当天新增长用户
	// 新用户增长数
	public function increse_order_user($date,$platform)
	{
		// select DISTINCT uid from ycb_jjsan_tradelog where uid in (SELECT DISTINCT uid from ycb_jjsan_tradelog
		// where from_unixtime(borrow_time,"%Y-%m-%d") = '2016-12-20' and status != 0) and from_unixtime(borrow_time,"%Y-%m-%d") < '2016-12-20' and status != 0
		$sql = "select count(distinct uid) as sum from ".DB::table("jjsan_tradelog")." where FROM_UNIXTIME(borrow_time,'%Y-%m-%d') = '{$date}' and status != 0 and tag in ({$this -> tag})"; // 单天有订单用户
		if($platform == 0){
			$sql .= " and platform in (0)";
		}
		if($platform == 1){
			$sql .= " and platform in (1,2)";
		}

		$total_user = DB::fetch_first($sql)['sum'];
		$sql = "select count(distinct a.uid) as sum from ".DB::table('jjsan_tradelog')." as a inner join ".DB::table('jjsan_tradelog')." as b on a.uid = b.uid ";
		$sql .= " where FROM_UNIXTIME(a.borrow_time,'%Y-%m-%d') = '{$date}' and FROM_UNIXTIME(b.borrow_time,'%Y-%m-%d') < '{$date}' and a.status != 0 and b.status != 0 and a.tag in ({$this -> tag}) and b.tag in ({$this -> tag})";
		if($platform == 0 ){ $sql .= " and a.platform in (0) ";}
		if($platform == 1){ $sql .= " and a.platform in (1,2) ";}
		$old_user = DB::fetch_first($sql)['sum'];
		return $total_user - $old_user;
	}

	// 新用户增长数，忽略平台，针对设备
	public function increse_station_order_user($beginTime, $endTime, $borrow_shop_station_id)
	{
		$sql = "select count(distinct uid) as sum from ".DB::table("jjsan_tradelog")." where borrow_time between '{$beginTime}' and '{$endTime}' and status > 0 and borrow_shop_station_id = '{$borrow_shop_station_id}' and tag in ({$this -> tag})"; // 单天有订单用户

		$total_user = DB::fetch_first($sql)['sum'];
		$sql = "select count(distinct a.uid) as sum from ".DB::table('jjsan_tradelog')." as a inner join ".DB::table('jjsan_tradelog')." as b on a.uid = b.uid ";
		$sql .= " where a.borrow_time between '{$beginTime}' and '{$endTime}' and b.borrow_time < '{$beginTime}' and a.status > 0 and b.status > 0 and a.tag in ({$this -> tag}) and b.tag in ({$this -> tag}) and a.borrow_shop_station_id = '{$borrow_shop_station_id}'";
		$old_user = DB::fetch_first($sql)['sum'];
		return $total_user - $old_user;
	}

}
