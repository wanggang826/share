<?php
namespace model;

use \C;
use \Exception;
use \Log;


class User
{

    private $ids = [];

    private $user;

    private $userinfo;

    private $user_weapp;

    public $count;  // 用户总数

    const EVENT_SUBSCRIBE           = 1;    // 用户关注事件
    const EVENT_UNSUBSCRIBE         = 2;    // 用户取关事件
    const EVENT_SCAN                = 3;    // 用户扫码事件
    const EVENT_SHOP_PAGE           = 4;    // 用户进入雨伞租赁页面
    const EVENT_SHOP_PAY            = 5;    // 用户在雨伞租赁页面支付
    const EVENT_TOP_UP              = 6;    // 用户充值
    const EVENT_WX_SLOT_LOCK        = 7;    // 微信端锁住槽位
    const EVENT_WX_SLOT_UNLOCK      = 8;    // 微信端解锁槽位
    const EVENT_WX_LEND             = 9;    // 微信端人工借出
    const EVENT_WX_REBIND           = 10;    // 微信端改绑商铺站点
    const EVENT_WX_ADD_SHOP         = 11;    // 微信端新增商铺并绑定
    const EVENT_WX_ADD_SHOP_STATION = 12;    // 微信端新增商铺站点并绑定
    const EVENT_WX_REPLACE          = 13;    // 微信端商铺站点换机
    const EVENT_WX_REMOVE           = 14;    // 微信端商铺站点撤机

    const MAX_WEAPP_SESSION_EXPIRE_TIME    = 1800;
    const MAX_PLATFORM_SESSION_EXPIRE_TIME = 1800;

    public function __construct()
    {
        $this->user         = ct('user');
        $this->userinfo     = ct('user_info');
        $this->user_log     = ct('user_log');
        $this->user_weapp   = ct('user_weapp');
        $this->user_session = ct('user_session');
    }

    public function setUser($ids)
    {
        if (is_numeric($ids)) {
            $this->ids[$ids] = [];
        } elseif (is_array($ids)) {
            foreach ($ids as $id) {
                $this->ids[$id] = [];
            }
        }
    }

    /*
        批量更新微信用户信息
    */
    public function update_all_user()
    {
        $page = 0;
        $size = 10;
        while ($users = $this->user->fetch_all_by_field('platform', 0, $page * $size, $size)) {
            $page += 1;
            if ($users) {
                foreach ($users as $user) {
                    $msg = $this->update_weixin_userinfo($user[id], $user[openid]);
                    file_put_contents(UPDATE_WEIXIN_USERINFO_LOG, $msg, FILE_APPEND);
                }
            }
        }
    }

    // 更新微信用户信息
    public function update_weixin_userinfo($id, $openid)
    {
        $userinfo = callWeiXinFuncV2("wxAPI::getUserInfo", [$openid]);
        if ($userinfo['subscribe'] == 1) {
            $data = [
                'id'             => $id,
                'openid'         => $userinfo['openid'],
                'nickname'       => json_encode($userinfo['nickname']),
                'sex'            => $userinfo['sex'],
                'city'           => $userinfo['city'],
                'province'       => $userinfo['province'],
                'country'        => $userinfo['country'],
                'headimgurl'     => $userinfo['headimgurl'],
                'update_time'    => date("Y-m-d H:i:s"),
                'language'       => $userinfo['language'],
                'subscribe_time' => $userinfo['subscribe_time'],
                'unionid'        => $userinfo['unionid'],
                'remark'         => $userinfo['remark'],
                'groupid'        => $userinfo['groupid'],
            ];
            $msg  = "更新id为$id , openid为: $openid , 名称为: {$userinfo['nickname']} 的用户!\n";
            $this->userinfo->insert($data, false, true);
            // 该用户已经取消关注,获取不到数据
        } elseif ($userinfo['subscribe'] == 0) {
            $msg = "id为$id , openid为: $openid 的用户已经取消关注!\n";
            $this->user->update($id, ['unsubscribe' => '1']);
        } else {
            $msg = '';
        }
        return $msg;
    }

    /*
        检查传参数组是否包含必须有的键值对
    */
    public static function _check_must_key($con, $key_arr)
    {
        foreach ($key_arr as $key) {
            if (!isset($con[$key])) {
                throw new Exception("少传了必须传的参数");
                return;
            }
        }
        return true;
    }

    public function count()
    {
        $this->count = $this->user->count();
        return $this->count;
    }

    // 通过 openid 获取用户信息
    public function get_by_openid($openid, $platform)
    {
        $user = $this->user->getAllByOpenid($openid, $platform);
        $temp = $this->userinfo->fetch_all($user[id]);
        $user = array_merge($temp[$user[id]], $user);
        return [$user];
    }

    /*
     * 通过用户名称查询
     * */
    public function get_by_nickname($nickname)
    {
        $users = $this->userinfo->get_by_field(['nickname', $nickname, 'like']);
        foreach ($users as $key => &$user) {
            $user_id = $user['id'];
            $temp    = $this->user->fetch_all($user_id);
            if ($temp) {
                $user = array_merge($temp[$user_id], $user);
            } else {
                unset($users[$key]);
            }
        }
        return $users;
    }

    /*
         加载第 n 页的用户
     */
    public function load_page($page, $page_size = false)
    {
        $limit = $page_size ?: 10;
        $start = ($page - 1) * $limit;
        $sort  = 'id asc';
        $users = $this->user->range($start, $limit, $sort);

        // merge userinfo
        foreach ($users as $key => &$user) {
            $temp = $this->userinfo->fetch_all($user[id]);
            if ($temp) {
                $user = array_merge($temp[$user[id]], $user);
            } else {
                unset($users[$key]);
            }
        }
        return $users;
    }

    // 用户关注事件
    public function user_subscribe($uid)
    {
        $data = [
            'uid'         => $uid,
            'type'        => self::EVENT_SUBSCRIBE,
            'detail'      => '',
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->user_log->insert($data, false, true);
        $this->user->update($uid, ['unsubscribe' => '0']);
    }

    // 用户取消关注事件
    public function user_unsubscribe($uid)
    {
        $data = [
            'uid'         => $uid,
            'type'        => self::EVENT_UNSUBSCRIBE,
            'detail'      => '',
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->user_log->insert($data, false, true);
        $this->user->update($uid, ['unsubscribe' => '1']);
    }

    // 用户扫码事件
    public function user_scan_log($uid)
    {
        $data = [
            'uid'         => $uid,
            'type'        => self::EVENT_SCAN,
            'detail'      => '',
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->user_log->insert($data, false, true);
    }

    // 用户进入雨伞页面事件
    public function user_in_shop_page($uid)
    {
        $data = [
            'uid'         => $uid,
            'type'        => self::EVENT_SHOP_PAGE,
            'detail'      => '',
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->user_log->insert($data, false, true);
    }

    // 用户在雨伞页面点击购买
    public function user_pay_event($uid)
    {
        $data = [
            'uid'         => $uid,
            'type'        => self::EVENT_SHOP_PAY,
            'detail'      => '',
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->user_log->insert($data, false, true);
    }

    // 用户充值事件
    // $num 充值数目
    public function user_top_up($uid, $num)
    {
        $data = [
            'uid'         => $uid,
            'type'        => self::EVENT_TOP_UP,
            'detail'      => json_encode(['fee' => $num]),
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->user_log->insert($data, false, true);
    }

    /*
        分页获取　用户所有信息
    */
    public function all($page, $page_size)
    {
        return $this->user->user_all_info_list($page, $page_size);
    }

    public function get_between_update_time($stime, $etime)
    {
        return $this->user->get_between_update_time($stime, $etime);
    }

    public function get_before_update_time($etime)
    {
        return $this->user->get_before_update_time($etime);
    }

    public function get_after_update_time($stime)
    {
        return $this->user->get_after_update_time($stime);
    }

    public function get_unreturned_order()
    {
        return $this->user->get_unreturned_order();
    }

    public function get_user_by_platform($platform)
    {
        return $this->user->get_user_by_platform($platform);
    }

    public function getUserInfoByOpenid($openid)
    {
        $rst = $this->user->fetch_by_field('openid', $openid);
        if ($rst) {
            return $rst;
        }
        throw new Exception('openid is not existed');
    }

    public function getUserIdByOpenid($openid)
    {
        $rst = $this->getUserInfoByOpenid($openid);
        if ($rst['id']) {
            return $rst['id'];
        }
        throw new Exception('openid is existed, but id is not existed');
    }

    public function checkInstallPermissionByOpenid($openid)
    {
        $id          = $this->getUserIdByOpenid($openid);
        $install_man = json_decode(C::t('common_setting')->fetch('jjsan_install_man'), true);
        if (!key_exists($id, $install_man)) throw new Exception('openid is not unauthorized');
    }

    public function log_slot_lock($uid, $sid, $slot_num)
    {
        $data = [
            'uid'         => $uid,
            'type'        => self::EVENT_WX_SLOT_LOCK,
            'detail'      => "锁住槽位 站点id: $sid; 槽位号: $slot_num",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->user_log->insert($data, false, true);
        return true;
    }

    public function log_slot_unlock($uid, $sid, $slot_num)
    {
        $data = [
            'uid'         => $uid,
            'type'        => self::EVENT_WX_SLOT_UNLOCK,
            'detail'      => "解锁槽位 站点id: $sid; 槽位号: $slot_num",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->user_log->insert($data, false, true);
        return true;
    }

    public function log_manually_lend($uid, $sid, $slot_num)
    {
        $data = [
            'uid'         => $uid,
            'type'        => self::EVENT_WX_LEND,
            'detail'      => "人工借出雨伞 站点id: $sid; 槽位号: $slot_num",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->user_log->insert($data, false, true);
        return true;
    }

    public function log_rebind($uid, $shop_station_id, $station_id)
    {
        $data = [
            'uid'         => $uid,
            'type'        => self::EVENT_WX_REBIND,
            'detail'      => "改绑商铺站点 商铺站点id: $shop_station_id; 站点id: $station_id",
            'create_time' => date("Y-m-d H:i:s", time()),
        ];
        $this->user_log->insert($data, false, true);
        return true;
    }

    public function log_add_shop($uid, $shop_station_id, $shop_id, $station_id)
    {
        $data = [
            'uid'         => $uid,
            'type'        => self::EVENT_WX_ADD_SHOP,
            'detail'      => "新增商铺 商铺站点id: $shop_station_id; 商铺id: $shop_id; 站点id: $station_id",
            'create_time' => date("Y-m-d H:i:s", time()),
        ];
        $this->user_log->insert($data, false, true);
        return true;
    }

    public function log_add_shop_station($uid, $shop_station_id, $shop_id, $station_id)
    {
        $data = [
            'uid'         => $uid,
            'type'        => self::EVENT_WX_ADD_SHOP_STATION,
            'detail'      => "新增商铺站点 商铺站点id: $shop_station_id; 商铺id: $shop_id; 站点id: $station_id",
            'create_time' => date("Y-m-d H:i:s", time()),
        ];
        $this->user_log->insert($data, false, true);
        return true;
    }

    public function log_shop_station_replace($uid, $shop_station_id, $origin_station_id, $new_station_id)
    {
        $data = [
            'uid'         => $uid,
            'type'        => self::EVENT_WX_REPLACE,
            'detail'      => "商铺站点换机 商铺站点id: $shop_station_id; 原站点id: $origin_station_id; 新站点id: $new_station_id",
            'create_time' => date("Y-m-d H:i:s", time()),
        ];
        $this->user_log->insert($data, false, true);
        return true;
    }

    public function log_station_remove($uid, $shop_station_id, $station_id)
    {
        $data = [
            'uid'         => $uid,
            'type'        => self::EVENT_WX_REMOVE,
            'detail'      => "商铺站点撤机 商铺站点id: $shop_station_id; 站点id: $station_id",
            'create_time' => date("Y-m-d H:i:s", time()),
        ];
        $this->user_log->insert($data, false, true);
        return true;
    }

    public function addUserByWechatPublic($openid)
    {
        LOG::DEBUG('add new user from wechat public');
        // 获取unionid, 检查否有小程序已经注册过了该用户
        $userInfo = callWeiXinFuncV2("wxAPI::getUserInfo", [$openid]);
        $uid      = $this->userinfo->where(['unionid' => $userInfo['unionid']])->select('id')->first()['id'];
        if (!empty($uid)) {
            $dbUser = $this->user->fetch($uid); // 数据库中的用户记录
        }
        $newUser = false;
        if (!empty($dbUser)) {
            // 小程序已经注册过, 更新openid即可
            LOG::DEBUG("add openid to user $uid : $openid");
            $this->user->update($uid, ['openid' => $openid]);
            $newUser = false;
        } else {
            // 新用户
            if (!empty($uid)) {
                $uid = $this->user->insert(['id' => $uid, 'openid' => $openid], true, false, true);
            } else {
                $uid = $this->user->insert(['openid' => $openid], true, false, true);
            }
            if (empty($uid)) {
                LOG::DEBUG("$openid has registered, correct error");
                $uid = $this->user->where(['openid' => $openid])->select('id')->first()['id'];
            } else {
                $newUser = true;
                LOG::DEBUG("new user $uid");
            }
        }

        if ($userInfo['subscribe'] == 1) {
            $data = [
                'id'             => $uid,
                'openid'         => $userInfo['openid'],
                'nickname'       => json_encode($userInfo['nickname']),
                'sex'            => $userInfo['sex'],
                'city'           => $userInfo['city'],
                'province'       => $userInfo['province'],
                'country'        => $userInfo['country'],
                'headimgurl'     => $userInfo['headimgurl'],
                'update_time'    => date("Y-m-d H:i:s", time()),
                'language'       => $userInfo['language'],
                'unionid'        => $userInfo['unionid'],
                'subscribe_time' => $userInfo['subscribe_time'],
                'remark'         => $userInfo['remark'],
                'groupid'        => $userInfo['groupid'],
            ];
            LOG::DEBUG("$uid , openid为: {$userInfo['openid']} , 名称为: {$userInfo['nickname']} 的用户!");
            $this->user->update($uid, ['unsubscribe' => 0]);
        } else {
            $this->user->update($uid, ['unsubscribe' => '1']);
            $data = [
                'id'          => $uid,
                'openid'      => $userInfo['openid'],
                'unionid'     => $userInfo['unionid'],
                'update_time' => date("Y-m-d H:i:s", time()),
            ];
        }

        if ($newUser) {
            $this->userinfo->insert($data, false, true);
        } else {
            $this->userinfo->update($uid, $data);
        }

        return $uid;
    }

    public function addNewUserByWeapp($weappUserData)
    {
        LOG::DEBUG('add new user from weapp');
        $uid = $this->userinfo->where(['unionid' => $weappUserData['unionId']])->select('id')->first()['id'];
        if (empty($uid)) {
            // 没有关注过公众号, 没有用户信息
            LOG::DEBUG('new user info for unionid:' . $weappUserData['unionId']);
            $uid = $this->user->insert(['unsubscribe' => 1], true); // 未关注公众号

            $data = [
                'id'          => $uid,
                'nickname'    => json_encode($weappUserData['nickName']),
                'sex'         => $weappUserData['gender'],
                'city'        => $weappUserData['city'],
                'province'    => $weappUserData['province'],
                'country'     => $weappUserData['country'],
                'headimgurl'  => $weappUserData['avatarUrl'],
                'language'    => $weappUserData['language'],
                'unionid'     => $weappUserData['unionId'],
                'update_time' => date("Y-m-d H:i:s", time()),
            ];

            $this->userinfo->insert($data, false, true);
        }
        LOG::DEBUG('add new weapp user');
        // 已关注过公众号
        $session = $this->_getSessionId($uid);
        $this->user_weapp->insert([
            'id'         => $uid,
            'openid'     => $weappUserData['openId'],
            'session'    => $session,
            'created_at' => date("Y-m-d H:i:s"),
            'updated_at' => date("Y-m-d H:i:s"),
        ], false, true);

        return ['user_id' => $uid, 'session' => $session];
    }

    /*
    * 开发者服务器使用登录凭证 code 获取 session_key 和 openid。
    * 其中 session_key 是对用户数据进行加密签名的密钥。为了自身应用安全，session_key 不应该在网络上传输
    * https://mp.weixin.qq.com/debug/wxadoc/dev/api/api-login.html
    * return 开发者服务器生成的session id
    */
    public function weappLogin($code, $encryptedData, $iv)
    {
        if (empty($code)) {
            return Api::make(Api::CODE_INVALID);
        }

        // 通过encryptedData获取openid和unionid
        $wxData = weappGetSessionKey($code);
        if (empty($wxData) || empty($wxData['openid'])) {
            LOG::ERROR('get weapp session key fail: ' . print_r($wxData, true));
            return Api::make(Api::CODE_INVALID);
        }

        // $wxData = ['openid' => 'xxx', 'session_key' => 'tiihtNczf5v6AKRyjwEUhQ=='];
        $userWeapp = ct('user_weapp')->where(['openid' => $wxData['openid']])->first();
        if ($userWeapp) {
            $session = $this->_getSessionId($userWeapp['id']);
            $this->user_weapp->update($userWeapp['id'], [
                'session'    => $session,
                'updated_at' => date("Y-m-d H:i:s"),
            ]);
            LOG::DEBUG("weapp api post by:" . $userWeapp['id'] . ", session:" . $session);

            return Api::make(Api::SUCCESS, ['session' => $session]);
        }

        // 第一次登录小程序, 需解密出unionid
        // $wxData = ['session_key' => 'tiihtNczf5v6AKRyjwEUhQ=='];
        //
        // $encryptedData="CiyLU1Aw2KjvrjMdj8YKliAjtP4gsMZM
        //                 QmRzooG2xrDcvSnxIMXFufNstNGTyaGS
        //                 9uT5geRa0W4oTOb1WT7fJlAC+oNPdbB+
        //                 3hVbJSRgv+4lGOETKUQz6OYStslQ142d
        //                 NCuabNPGBzlooOmB231qMM85d2/fV6Ch
        //                 evvXvQP8Hkue1poOFtnEtpyxVLW1zAo6
        //                 /1Xx1COxFvrc2d7UL/lmHInNlxuacJXw
        //                 u0fjpXfz/YqYzBIBzD6WUfTIF9GRHpOn
        //                 /Hz7saL8xz+W//FRAUid1OksQaQx4CMs
        //                 8LOddcQhULW4ucetDf96JcR3g0gfRK4P
        //                 C7E/r7Z6xNrXd2UIeorGj5Ef7b1pJAYB
        //                 6Y5anaHqZ9J6nKEBvB4DnNLIVWSgARns
        //                 /8wR2SiRS7MNACwTyrGvt9ts8p12PKFd
        //                 lqYTopNHR1Vf7XjfhQlVsAJdNiKdYmYV
        //                 oKlaRv85IfVunYzO0IKXsyl7JCUjCpoG
        //                 20f0a04COwfneQAGGwd5oa+T8yO5hzuy
        //                 Db/XcxxmK01EpqOyuxINew==";
        //
        // $iv = 'r7BXXKkLb8qrSNn05n0qiA==';

        $decryptData = weappDecryptBizData($wxData['session_key'], $encryptedData, $iv);
        if (empty($decryptData['unionId'])) {
            LOG::ERROR('fail to decrypt weapp data, ret: ' . print_r($decryptData, true));
            return Api::make(Api::ENCRYPTED_DATA_INVALID);
        }

        $ret     = $this->addNewUserByWeapp($decryptData);
        $session = $ret['session'];

        LOG::DEBUG("weapp api post by:" . $ret['user_id'] . ", session:" . $session);
        return Api::make(Api::SUCCESS, ['session' => $session]);
    }

    /*
    * 开发者服务器使用登录凭证 code 获取 session。
    * return 开发者服务器生成的session id
    */
    public function platformLogin($code)
    {
        if (empty($code)) {
            return Api::make(Api::CODE_INVALID);
        }

        // 没有uid的话, 走正常通道获取openid
        // 这种情况适用于线上测试环境
        $platform = getPlatform();
        switch ($platform) {
            # 微信平台
            case PLATFORM_WX:
                require_once JJSAN_DIR_PATH . 'lib/wxpay.class.php';
                $jspay  = new \JsApiPay();
                $openid = $jspay->GetOpenidFromMp($code);
                if (isset($openid['errcode'])) {
                    return Api::make(Api::CODE_INVALID);
                }
                LOG::DEBUG('the wechat user\'s openid is ' . $openid);
                $user = $this->user->where(['openid' => $openid])->first();
                if (!$user || $user['unsubscribe'] == 1) {
                    return Api::make(2, [], '用户未关注公众号');
                }
                $uid       = $user['id'];
                $user_info = $this->userinfo->fetch($uid);
                $session   = $this->_getSessionId($uid);
                $unreturn  = ct('tradelog')->unreturn($uid);
                ct('user_session')->insert([
                    'uid'         => $uid,
                    'session'     => $session,
                    'update_time' => date('Y-m-d H:i:s'),
                    'create_time' => date('Y-m-d H:i:s'),
                ]);
                $ret      = [
                    'session'    => $session,
                    'nickname'   => json_decode($user_info['nickname'], 1),
                    'headimgurl' => $user_info['headimgurl'],
                    'money'      => $user['usablemoney'] + $user['deposit'],
                    'unreturn'   => $unreturn,
                ];
                $installs = json_decode(C::t('common_setting')->fetch('jjsan_install_man'), true);
                $users    = json_decode(C::t('common_setting')->fetch('jjsan_install_man_user'), true);
                if (array_key_exists($uid, $installs)) {
                    $ret['installer'] = 1;
                }
                if (array_key_exists($uid, $users)) {
                    $ret['installer'] = 0;
                }
                return Api::make(Api::SUCCESS, $ret);
                break;

            # 支付宝平台
            case PLATFORM_ALIPAY:
                // 支付宝实际传的是auth_code，前端处理成了code
                require_once JJSAN_DIR_PATH . 'lib/alipay/AlipayAPI.php';
                $openid = \AlipayAPI::getOpenidFromMp($code);
                if (!$openid) {
                    return Api::make(Api::CODE_INVALID);
                }
                LOG::DEBUG('the alipay user\'s openid is ' . $openid);
                $user      = $this->user->where(['openid' => $openid])->first();
                $uid       = $user['id'];
                $user_info = $this->userinfo->fetch($uid);
                // 默认不更新用户数据，这是个标记
                $updateFlag = false;
                // 记录不存在或者7天前的数据，更新用户数据
                if (!$user_info || strtotime($user_info['update_time']) < strtotime('-7 days')) {
                    $updateFlag  = true;
                    $newUserInfo = \AlipayAPI::getUserInfoAfterGetOpenid();
                    LOG::INFO('alipay.user.info.share response is: ' . print_r($newUserInfo, 1));

                    //说明下 上面API返回值如果不存在昵称和头像的话，对象中没有相应的公共属性
                    //所以保存数据的时候需要用isset判断后 再保存到数据库

                    switch (strtolower($newUserInfo->gender)) {
                        case 'm':
                            $sex = 1;
                            break;
                        case 'f':
                            $sex = 2;
                            break;
                        default:
                            $sex = 0;
                    }
                    $data['id']          = $uid; // 主键必须要自定义, 要与user表主键统一
                    $data['openid']      = $newUserInfo->user_id;
                    $data['nickname']    = isset($newUserInfo->nick_name) ? json_encode($newUserInfo->nick_name) : '';
                    $data['sex']         = $sex;
                    $data['province']    = isset($newUserInfo->province) ? $newUserInfo->province : '';
                    $data['city']        = isset($newUserInfo->city) ? $newUserInfo->city : '';
                    $data['country']     = "中国";
                    $data['headimgurl']  = isset($newUserInfo->avatar) ? $newUserInfo->avatar : '';
                    $data['update_time'] = date("Y-m-d H:i:s");
                    if (!$user_info) {
                        // 保存用户信息
                        ct('user_info')->insert($data);
                        LOG::INFO('insert new user info, ' . print_r($data, 1));
                    } else {
                        // 更新用户信息
                        ct('user_info')->update($uid, $data);
                        LOG::INFO('update user info, uid: ' . $uid . ' , data: ' . print_r($data, 1));
                    }

                }
                LOG::DEBUG('user\'s id is ' . $uid);
                $session  = $this->_getSessionId($uid);
                $unreturn = ct('tradelog')->unreturn($uid);
                ct('user_session')->insert([
                    'uid'         => $uid,
                    'session'     => $session,
                    'update_time' => date('Y-m-d H:i:s'),
                    'create_time' => date('Y-m-d H:i:s'),
                ]);
                $ret = [
                    'session'    => $session,
                    'nickname'   => $updateFlag ? (empty($data['nickname']) ? '' : json_decode($data['nickname'], 1)) : (empty($user_info['nickname']) ? '' : json_decode($user_info['nickname'], 1)),
                    'headimgurl' => $updateFlag ? $data['headimgurl'] : $user_info['headimgurl'],
                    'money'      => $user['usablemoney'] + $user['deposit'],
                    'unreturn'   => $unreturn,
                ];
                return Api::make(Api::SUCCESS, $ret);
                break;

            case PLATFORM_NO_SUPPORT:

            default:
                return Api::make(Api::ERROR_UNKNOWN);
        }
    }

    // 公众号API： 用户钱包+用户未归还订单数+installer
    public function userInfoForPlatform($uid)
    {
        $user     = ct('user')->fetch($uid);
        $unreturn = ct('tradelog')->unreturn($uid);
        $ret      = [
            'money'    => $user['usablemoney'] + $user['deposit'],
            'unreturn' => $unreturn,
        ];
        $installs = json_decode(C::t('common_setting')->fetch('jjsan_install_man'), true);
        $users    = json_decode(C::t('common_setting')->fetch('jjsan_install_man_user'), true);
        if (array_key_exists($uid, $installs)) {
            $ret['installer'] = 1;
        }
        if (array_key_exists($uid, $users)) {
            $ret['installer'] = 0;
        }
        return $ret;
    }

    // 小程序API： 用户钱包+用户未归还订单数
    public function userInfoForWeapp($uid)
    {
        $user     = ct('user')->fetch($uid);
        $unreturn = ct('tradelog')->unreturn($uid);
        $ret      = [
            'money'    => $user['usablemoney'] + $user['deposit'],
            'unreturn' => $unreturn,
        ];
        return $ret;
    }

    /**
     * 验证公众号/生活号session是否过期
     *
     * @param $session
     * @return bool 失败返回false，成功返回uid(同时更新过期时间)
     */
    public function checkPlatformSession($session)
    {
        $userSession = $this->user_session->where(['session' => $session])->first();
        if (empty($userSession) || time() - strtotime($userSession['update_time']) > User::MAX_PLATFORM_SESSION_EXPIRE_TIME) {
            // session 过期
            return false;
        }
        // 更新 session
        $this->user_session->update($userSession['id'], ['update_time' => date("Y-m-d H:i:s")]);
        return $userSession['uid'];
    }



    /**
     * 检查微信小程序session是否过期
     * @param string $session
     * @return boolean | integer 失败返回false，成功返回uid(同时更新过期时间)
     */
    public function checkWeappLogin($session)
    {
        $userWeapp = $this->user_weapp->where(['session' => $session])->first();
        if (empty($userWeapp) || time() - strtotime($userWeapp['updated_at']) > User::MAX_WEAPP_SESSION_EXPIRE_TIME) {
            // session 过期
            return false;
        } else {
            // 更新 session
            $this->user_weapp->update($userWeapp['id'], ['updated_at' => date("Y-m-d H:i:s")]);
            return $userWeapp['id'];
        }
    }

    private function _getSessionId($uid)
    {
        return md5($uid . microtime(true) . mt_rand());
    }
}

