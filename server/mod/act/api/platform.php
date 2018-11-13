<?php

use model\Api;
use model\User;
use model\Shop;

if (ENV_DEV) {
    header('Access-Control-Allow-Origin:*');
    header('Access-Control-Allow-Methods: POST, GET');
    header('Access-Control-Allow-Headers:x-requested-with,content-type');
}

// 这里是微信公众号和支付宝生活号的接口
// 接口返回样式：['code' => 0, 'msg' => '成功', 'data' => ['nickname' => 'xMan', ... ]]
// code 状态码
// msg  状态码描述
// data 返回内容

LOG::DEBUG('api request : ' . json_encode($_GET));

$user = new User();

// 在调用非login和get_appid接口时，需要对session进行验证
if ($opt != 'login' && $opt != 'get_appid') {
    if (!(Api::check($_GET, ['session']))) {
        LOG::INFO('need session');
        Api::fail(Api::NO_MUST_PARAM);
        exit;
    }
    // 过期返回false，成功返回uid
    if (!$uid = $user->checkPlatformSession($session)) {
        LOG::INFO('session expired : ' . $session);
        Api::fail(Api::SESSION_EXPIRED);
        exit;
    }
    LOG::DEBUG("normal api post by: " . $uid);
}

const PAGE_SIZE = 20;

switch ($opt) {
    // 登录接口，用获取的code进行session记录
    case 'login':
        if (!Api::check($_GET, ['code'])) {
            Api::fail(Api::NO_MUST_PARAM);
            exit;
        }
        $ret = $user->platformLogin($code);
        Api::outputJSON($ret);
        break;

    // 获取用户金额和未还订单数 installer
    case 'userinfo':
        $userInfo = $user->userInfoForPlatform($uid);
        Api::output($userInfo);
        break;

    // 根据地理位置获取附近商铺信息
    case 'get_shops':
        if (!(Api::check($_GET, ['lng', 'lat']))) {
            Api::fail(Api::NO_MUST_PARAM);
            break;
        }
        $ret = ct('shop_station')->shopsForMap($lng, $lat);
        if ($ret === false) {
            Api::fail(3, '失败');
            break;
        }
        $ret ? Api::output($ret) : Api::fail(2, '附近没有商铺');
        break;

    // 获取特定商铺详细信息
    case 'shop_detail':
        if (!(Api::check($_GET, ['shop_station_id']))) {
            Api::fail(Api::NO_MUST_PARAM);
            break;
        }
        $shop            = new Shop();
        $shopStationInfo = ct('shop_station')->fetch($shop_station_id);
        $shopid          = $shopStationInfo['shopid'];

        $hasShopFlag = false;
        //　该商铺下的所有站点
        if ($shopid) {
            $shop_stations    = $shop->shop_stations($shopid, 0, 0, table_jjsan_shop_station::ENABLE);
            $shop_station_ids = array_column($shop_stations, 'id');
            $hasShopFlag      = true;
        } else {
            $shop_station_ids[] = $shop_station_id;
        }

        // 显示规则
        // 有商铺时 商铺信息用商铺数据显示
        // 无商铺时 商铺信息用当前商铺站点数据显示

        if ($hasShopFlag) {
            $shopInfo = ct('shop')->fetch($shopid);

            $shopData['id']       = $shopInfo['id'];
            $shopData['name']     = $shopInfo['name'];
            $shopData['phone']    = $shopInfo['phone'];
            $shopData['stime']    = $shopInfo['stime'];
            $shopData['etime']    = $shopInfo['etime'];
            $shopData['carousel'] = json_decode($shopInfo['carousel'], 1) ?: [];

            // 处理直辖市的特殊情况
            if ($shopInfo['province'] && ($shopInfo['province'] == $shopInfo['city'])) {
                $shopData['address'] = $shopInfo['province'] . $shopInfo['area'] . $shopInfo['locate'];
            } else {
                $shopData['address'] = $shopInfo['province'] . $shopInfo['city'] . $shopInfo['area'] . $shopInfo['locate'];
            }

            $stations = ct('shop_station')->getPartInfoForApi($shop_station_ids);

            $ret = ['stations' => $stations, 'shop_info' => $shopData];
        } else {

            $stationInfo = ct('station')->fetch($shopStationInfo['station_id']);
            $stations[]  = [
                'station_id' => $shopStationInfo['station_id'],
                'desc'       => $shopStationInfo['desc'],
                'empty'      => $stationInfo['empty'],
                'usable'     => $stationInfo['usable'],
            ];
            $shopInfo    = [
                'id'       => 0,
                'name'     => $shopStationInfo['title'],
                'address'  => $shopStationInfo['address'],
                'phone'    => '',
                'stime'    => '',
                'etime'    => '',
                'carousel' => [],
            ];

            $ret = ['stations' => $stations, 'shop_info' => $shopInfo];
        }

        Api::output($ret);
        break;

    // 根据关键字搜索商铺
    case 'filter':
        if (!(Api::check($_GET, ['key_str', 'mark']))) {
            Api::fail(Api::NO_MUST_PARAM);
            break;
        }

        $page_size     = 30;
        $shop_stations = DB::fetch_all('SELECT `id`, `shopid`,`longitude`, `latitude` FROM %t WHERE %i AND (%i OR %i) ', [
            'jjsan_shop_station',
            DB::field('status', 1),
            DB::field('address', '%' . $key_str . '%', 'like'),
            DB::field('title', '%' . $key_str . '%', 'like'),
        ]);

        $shop_station_ids = array_column($shop_stations, 'id');

        $ret   = ct('shop_station')->filter($shop_station_ids, $mark, $page_size);
        $shops = $ret['shops'];

        if (empty($shops)) {
            Api::fail(2, '没有搜索结果');
            break;
        }

        // 没有商铺logo就使用商铺类型里面的logo
        $shopTypes = ct('shop_type')->get();
        foreach ($shopTypes as $v) {
            $newShopTypes[$v['id']] = json_decode($v['logo']);
        }
        $shops = array_map(function ($a) use ($newShopTypes) {
            if (!$a['shoplogo']) {
                $a['shoplogo'] = $newShopTypes[$a['type']];
            }
            return $a;
        }, $shops);

        foreach ($shop_stations as $k => $v) {
            foreach ($shops as $kk => $shop) {
                if (!in_array($v['shopid'], $sid) && $v['shopid'] == $shop['shopid']) {
                    $shops[$kk]['lng'] = $v['longitude'];
                    $shops[$kk]['lat'] = $v['latitude'];
                }
            }
        }

        $shops = array_values($shops);
        $more  = count($shops) < $page_size ? 0 : 1;
        Api::output(['shops' => $shops, 'mark' => $ret['mark'], 'more' => $more]);
        break;

    // 单条订单数据
    case 'order_data':
        if (!(Api::check($_GET, ['order_id']))) {
            Api::fail(Api::NO_MUST_PARAM);
            break;
        }
        $num = ct('tradelog')->unreturn($uid);
        Api::output(['num' => $num]);
        break;

    // 钱包信息
    case 'wallet':
        $user  = ct('user')->fetch($uid);
        $price = ct('menu')->fetch(1)['price'];
        $ret   = [
            'usable'  => $user['usablemoney'],
            'deposit' => $user['deposit'],
            'price'   => $price,
        ];
        Api::output($ret);
        break;

    // 钱包明细
    case 'wallet_detail':
        if (!(Api::check($_GET, ['page']))) {
            Api::fail(Api::NO_MUST_PARAM);
            break;
        }
        $platform = ct('user')->fetch($uid)['platform'];
        $ret      = ct('wallet_statement')->get_statement($uid, max($page - 1, 0) * PAGE_SIZE, PAGE_SIZE);
        if ($ret) {
            // 状态为4(提现到账)时要判断是否超过2天，不超过2天变更状态为3(提现处理中)
            $ret = array_map(function ($a) {
                if (isset($a['type']) && $a['type'] == WALLET_TYPE_WITHDRAW) {
                    if (strtotime($a['time']) > time() - 2 * 24 * 3600) {
                        $a['type'] = WALLET_TYPE_REQUEST;
                    }
                }
                return $a;
            }, $ret);
            Api::output($ret);
        } else {
            Api::fail(2, '没有更多记录');
        }
        break;

    // 遗失处理
    case 'loss_handle':
        if (!(Api::check($_GET, ['order_id']))) {
            Api::fail(Api::NO_MUST_PARAM);
            break;
        }
        $where['orderid'] = $order_id;
        $where['uid']     = $uid;
        $where['status']  = [ORDER_STATUS_RENT_CONFIRM, ORDER_STATUS_RENT_CONFIRM_FIRST];
        // 应该避免借出第一次确认与借出确认这段时间内用户提交遗失请求
        // 简单一点的方法就是禁止遗失最后更新时间2分钟之内的订单
        $where['lastupdate'] = ['value' => time() - 120, 'glue' => '<'];

        $order_info = ct('tradelog')->where($where)->first();
        if (!$order_info || ($order_info['price'] <= 0)) {
            LOG::WARN('order id: ' . $order_id . ' loss unauthorized by uid: ' . $uid);
            Api::fail(2, '操作失败');
            break;
        }

        $isZhimaOrder = $order_info['platform'] == PLATFORM_ZHIMA ? 1 : 0;

        ct('tradelog')->update($order_id, [
            'status'      => ORDER_STATUS_LOSS,
            'lastupdate'  => time(),
            'return_time' => time(),
            'usefee'      => $order_info['price'],
        ]);

        if ($isZhimaOrder) {
            // 芝麻订单
            ct('trade_zhima')->update($order_id, ['status' => ZHIMA_ORDER_COMPLETE_WAIT, 'update_time' => time()]);
            // 记录用户流水
            ct('wallet_statement')->insert([
                'uid'        => $uid,
                'related_id' => $order_id,
                'type'       => WALLET_TYPE_ZHIMA_PAID_UNCONFIRMED,
                'amount'     => $order_info['price'],
                'time'       => date('Y-m-d H:i:s'),
            ]);
        } else {
            // 非芝麻订单，扣除押金，可用余额不变
            ct('user')->reduceDeposit($order_info['uid'], $order_info['price']);
            // 记录用户流水
            ct('wallet_statement')->insert([
                'uid'        => $uid,
                'related_id' => $order_id,
                'type'       => WALLET_TYPE_PAID,
                'amount'     => $order_info['price'] + 0, //加0
                'time'       => date('Y-m-d H:i:s'),
            ]);
        }

        // 推送雨伞遗失处理信息
        $msg = [
            'openid'              => $order_info['openid'],
            'borrow_station_name' => $order_info['borrow_station_name'],
            'borrow_time'         => date('Y-m-d H:i:s', $order_info['borrow_time']),
            'handle_time'         => date('Y-m-d H:i:s'),
            'order_id'            => $order_id,
            'price'               => $order_info['price'],
        ];
        addMsgToQueue($order_info['platform'], TEMPLATE_TYPE_LOSE_UMBRELLA, $msg);
        $orderInfo = ct('tradelog')->getOrderDataForApi($order_id);
        Api::output($orderInfo);
        break;

    // 根据二维码返回特定站点信息
    case 'get_station_info':
        if (!(Api::check($_GET, ['qrcode']))) {
            Api::fail(Api::NO_MUST_PARAM);
            break;
        }
        LOG::DEBUG('qrcode url: ' . $qrcode);
        $qrcode    = urldecode($qrcode);
        $platform  = ct('user')->fetch($uid)['platform'];
        $stationId = 0;
        $ret       = borrowForApi($qrcode, $uid, $stationId, $platform);
        Api::outputJSON($ret);
        break;

    // 提现申请
    case 'refund':
        $ret = refundRequest($uid);
        Api::outputJSON($ret);
        break;

    // 借还记录
    case 'orders':
        if (!(Api::check($_GET, ['page']))) {
            Api::fail(Api::NO_MUST_PARAM);
            break;
        }

        $ret = ct("tradelog")->getUserOrders($uid, max($page - 1, 0) * PAGE_SIZE, PAGE_SIZE);
        if (empty($ret)) {
            Api::fail(2, '没有记录');
        } else {
            Api::output($ret);
        }
        break;

    // 订单状态查询
    case 'order_status':
        if (!(Api::check($_GET, ['order_id']))) {
            Api::fail(Api::NO_MUST_PARAM);
            break;
        }
        $order_info = ct('tradelog')->where(['orderid' => $order_id, 'uid' => $uid])->first();
        // 订单不存在，订单已归还，前端跳走
        if (!$order_info || $order_info['status'] == ORDER_STATUS_RETURN) {
            Api::output(['status' => 7]);
            break;
        }
        // 借出时间超过2分钟，前端跳走
        if (time() - $order_info['borrow_time'] > 120) {
            Api::output(['status' => 7]);
            break;
        }
        // 订单未支付
        if ($order_info['status'] == ORDER_STATUS_WAIT_PAY) {
            Api::output(['status' => 0]);
            break;
        }
        // 订单已支付
        if ($order_info['status'] == ORDER_STATUS_PAID) {
            Api::output(['status' => 1]);
            break;
        }
        // 机器出伞中
        if ($order_info['status'] == ORDER_STATUS_RENT_CONFIRM_FIRST) {
            $message = unserialize($order_info['message']);
            Api::output(['status' => 3, 'slot' => $message['slot']]);
            break;
        }
        // 雨伞借出成功
        if ($order_info['status'] == ORDER_STATUS_RENT_CONFIRM) {
            Api::output(['status' => 2]);
            break;
        }
        // 用户未取走（含中间态）
        if (in_array($order_info['status'], [ORDER_STATUS_RENT_NOT_FETCH_INTERMEDIATE, ORDER_STATUS_RENT_NOT_FETCH])) {
            Api::output(['status' => 4]);
            break;
        }
        // 机器被人使用中导致借伞失败
        if ($order_info['status'] == ORDER_STATUS_LAST_ORDER_UNFINISHED) {
            Api::output(['status' => 6]);
            break;
        }
        // 其他情况导致机器出伞失败
        Api::output(['status' => 5]);
        break;

    # 角色切换, 管理人员使用
    case 'switch_role':
        $installs = json_decode(C::t('common_setting')->fetch('jjsan_install_man'), true);
        $users    = json_decode(C::t('common_setting')->fetch('jjsan_install_man_user'), true);
        if (array_key_exists($uid, $installs)) {
            $flag          = 'install';
            $current_role  = lang('plugin/jjsan', 'install_man');
            $checkout_role = lang('plugin/jjsan', 'user');
            $users[$uid]   = $installs[$uid];
            unset($installs[$uid]);
            C::t('common_setting')->update('jjsan_install_man_user', json_encode($users));
            C::t('common_setting')->update('jjsan_install_man', json_encode($installs));
        } elseif (array_key_exists($uid, $users)) {
            $flag           = 'user';
            $current_role   = lang('plugin/jjsan', 'user');
            $checkout_role  = lang('plugin/jjsan', 'install_man');
            $installs[$uid] = $users[$uid];
            unset($users[$uid]);
            C::t('common_setting')->update('jjsan_install_man_user', json_encode($users));
            C::t('common_setting')->update('jjsan_install_man', json_encode($installs));
        } else {
            LOG::WARN('switch role unauthorized, uid: ' . $uid);
            Api::fail(2, '没有权限');
            break;
        }
        if (array_key_exists($uid, $installs)) {
            $res['installer'] = 1;
        }
        if (array_key_exists($uid, $users)) {
            $res['installer'] = 0;
        }
        Api::output($res);
        break;

    // 获取Appid
    case 'get_appid':
        if (!(Api::check($_GET, ['platform']))) {
            Api::fail(Api::NO_MUST_PARAM);
            break;
        }
        if ($platform == PLATFORM_WX) {
            // 调用扫一扫接口用
            $ret = ['appId' => AppID];
        } else {
            $ret = ['appId' => ALIPAY_APPID];
        }
        Api::output($ret);
        break;

    // 借伞
    case 'borrow':
        if (!(Api::check($_GET, ['stationid']))) {
            Api::fail(Api::NO_MUST_PARAM);
            exit;
        }
        LOG::DEBUG('pay uid is : ' . $uid);
        $ret = getPayInfo($stationid, $uid);
        Api::outputJSON($ret);
        break;

    case 'get_wechat_jsapi':
        if (!(Api::check($_GET, ['url']))) {
            Api::fail(Api::NO_MUST_PARAM);
            exit;
        }
        if (ct('user')->fetch($uid)['platform'] != PLATFORM_WX) {
            // 非微信请求传空
            Api::output([]);
            break;
        }
        $jt = getJsApiTicketValue();
        if (!$jt) {
            Api::fail(3, '服务器异常');
            break;
        }
        $ret = wxAPI::GetSignPackage($jt, $url);
        Api::output($ret);
        break;

    case "change_baidu_coordinates_to_gaode":
        $data['key']       = GAODE_MAP_KEY_FOR_API;
        $data['locations'] = "$lng,$lat";
        $data['coordsys']  = "baidu";
        $data['output']    = "JSON";
        $api               = "http://restapi.amap.com/v3/assistant/coordinate/convert";
        $scurl             = new sCurl($api, 'GET', $data);
        $ret               = json_decode($scurl->sendRequest(), true);
        if ($ret['status'] != 1) {
            LOG::INFO('change coordinates fail');
            LOG::INFO(print_r($ret, 1));
            Api::fail(1, '地址转换失败');
            break;
        }
        list($newLng, $newLat) = explode(',', $ret['locations']);
        Api::output(['lng' => $newLng, 'lat' => $newLat]);
        break;

    case 'convert_gps_to_baidu':
        if (!(Api::check($_GET, ['lng', 'lat']))) {
            Api::fail(Api::NO_MUST_PARAM);
            exit;
        }
        require_once JJSAN_DIR_PATH . 'lib/lbsapi.class.php';
        $rst = lbsAPI::convertGps($lng . ',' . $lat);
        Log::INFO('covert gps to baidu : ' . print_r($rst, 1));
        if ($rst['status'] != 0) {
            Api::fail(1, '转换失败');
            exit;
        }
        Api::output(['lng' => $rst['result'][0]['x'], 'lat' => $rst['result'][0]['y']]);
        break;

    default:
        Api::fail(Api::API_NOT_EXISTS); // api不存在
        break;
}

LOG::DEBUG('api response : ' . print_r(Api::getLogStr(), 1));
exit;