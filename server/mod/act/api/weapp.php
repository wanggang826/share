<?php

use model\Api;
use model\User;
use model\Shop;

require_once JJSAN_DIR_PATH . "lib/weapi.class.php";

$user = new User();

LOG::DEBUG('weapp request : ' . json_encode($_GET));

// 在调用非login接口时，对session进行验证
if ($opt != 'login') {
    if (!(Api::check($_GET, ['session']))) {
        Api::fail(Api::NO_MUST_PARAM);
        exit;
    }
    $uid = $user->checkWeappLogin($_GET['session']);
    if (!$uid) {
        Api::fail(Api::SESSION_EXPIRED);
        exit;
    }
    $GLOBALS['platform'] = PLATFORM_WEAPP;
    LOG::DEBUG("weapp api post by:" . $uid);
}
// 分页记录条目数
const PAGE_SIZE = 20;

switch ($opt) {

    // 登录接口，用获取的code进行session记录
    case 'login':
        if (!Api::check($_GET, ['code', 'encryptedData', 'iv'])) {
            Api::fail(Api::NO_MUST_PARAM);
        } else {
            $ret = $user->weappLogin($_GET['code'], $_GET['encryptedData'], $_GET['iv']);
            Api::outputJSON($ret);
        }
        break;

    // 根据地理位置获取附近商铺信息
    case 'get_shops' :
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
    case 'detail' :
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

            $ret = ['stations' => $stations, 'shopInfo' => $shopInfo];
        }

        Api::output($ret);
        break;

    // 搜索商铺
    case 'filter':
        if (!(Api::check($_GET, ['key_str', 'mark']))) {
            Api::fail(Api::NO_MUST_PARAM);
            break;
        }

        $page_size        = 30;
        $shop_station_ids = DB::fetch_all('SELECT `id` FROM %t WHERE %i AND (%i OR %i) ', [
            'jjsan_shop_station',
            DB::field('status', 1),
            DB::field('address', '%' . $key_str . '%', 'like'),
            DB::field('title', '%' . $key_str . '%', 'like'),
        ]);

        $shop_station_ids = array_column($shop_station_ids, 'id');

        $ret   = ct('shop_station')->filter($shop_station_ids, $mark, $page_size);
        $shops = $ret['shops'];

        if (empty($shops)) {
            Api::fail(2, '没有搜索结果');
            break;
        }

        foreach ($shops as $k => $shop) {
            if (!$shop['shoplogo']) {
                $type             = $shop['type'];
                $type_info        = ct('shop_type')->fetch($type);
                $shop['shoplogo'] = json_decode($type_info['logo']);
            }
        }

        foreach ($shop_stations as $k => $v) {
            foreach ($shops as $kk => $shop) {
                if (!in_array($v['shopid'], $sid) && $v['shopid'] == $shop['shopid']) {
                    $shops[$kk]['lng'] = $v['longitude'];
                    $shops[$kk]['lat'] = $v['latitude'];
                }
            }
        }

        $shops = array_values($shops);
        Api::output(['shops' => $shops, 'mark' => $ret['mark']]);
        break;

    // 钱包
    case 'wallet':
        $user = ct('user')->fetch($uid);
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

    // 用户信息
    case 'user_info':
        $ret = $user->userInfoForWeapp($uid);
        Api::output($ret);
        break;

    // 提现申请
    case 'refund':
        $ret = refundRequest($uid, true);
        Api::outputJSON($ret);
        break;

    // 借还记录
    case 'orders':
        if (!(Api::check($_GET, ['page']))) {
            Api::fail(Api::NO_MUST_PARAM);
            break;
        }

        $ret = ct("tradelog")->getUserOrders($uid, max($page - 1, 0) * PAGE_SIZE, PAGE_SIZE);

        if ($ret) {
            echo Api::output($ret);
            break;
        } elseif (empty($ret)) {
            Api::fail(2, '没有记录');
            break;
        }

        Api::fail(Api::ERROR_UNKNOWN);
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

        $orderInfo = ct('tradelog')->where($where)->first();
        if (!$orderInfo || ($orderInfo['price'] <= 0)) {
            LOG::WARN('order id: ' . $order_id . ' loss unauthorized by uid: ' . $uid);
            Api::fail(2, '操作失败');
            break;
        }
        ct('tradelog')->update($order_id, [
            'status'      => ORDER_STATUS_LOSS,
            'lastupdate'  => time(),
            'return_time' => time(),
            'usefee'      => $orderInfo['price'],
        ]);
        ct('user')->reduceDeposit($orderInfo['uid'], $orderInfo['price']);
        // 记录用户流水
        ct('wallet_statement')->insert([
            'uid'        => $uid,
            'related_id' => $order_id,
            'type'       => WALLET_TYPE_PAID,
            'amount'     => $orderInfo['price'] + 0, //加0
            'time'       => date('Y-m-d H:i:s'),
        ]);

        // 推送雨伞遗失处理信息
        $msg = [
            'openid'         => $orderInfo['openid'],
            'borrow_station_name' => $orderInfo['borrow_station_name'],
            'borrow_time'    => date('Y-m-d H:i:s', $orderInfo['borrow_time']),
            'handle_time'    => date('Y-m-d H:i:s'),
            'order_id'       => $order_id,
        ];
        addMsgToQueue(PLATFORM_WEAPP, TEMPLATE_TYPE_LOSE_UMBRELLA, $msg);
        $orderInfo = ct('tradelog')->getOrderDataForApi($order_id);
        Api::output($orderInfo);
        break;

    // 根据二维码返回特定站点信息
    case 'borrow':
        if (!(Api::check($_GET, ['qrcode']))) {
            Api::fail(Api::NO_MUST_PARAM);
            break;
        }
        LOG::DEBUG('qrcode : ' . $qrcode);
        $qrcode    = urldecode($_GET['qrcode']);
        $input     = explode("/", $qrcode);    //if oneQrcode , $input = [ 0=>"https:",1=>"", 2=>"weixin.qq.com", 3=>"q", 4=>"1653"]
        $oneQrcode = (in_array("q", $input) && is_numeric($input[4])) ? true : false; // 是否是二码合一
        if ($oneQrcode) {
            $sid = $input[4];
        } else {
            $qrInfo = ct('qrcode')->where(['wx' => $qrcode])->first();
            if (!$qrInfo) {
                Api::fail(Api::ERROR_QR_CODE);
                break;
            }
            $sid = $qrInfo['id'];
        }

        $ret = borrowForApi($qrcode, $uid, $sid + 0, PLATFORM_WEAPP);
        Api::outputJSON($ret);
        break;

    // 支付押金借伞
    case 'wxpay':
        if (!(Api::check($_GET, ['sid']))) {
            Api::fail(Api::NO_MUST_PARAM);
            break;
        }

        $ret = getPayInfo($sid+0, $uid, PLATFORM_WEAPP);
        Api::outputJSON($ret);
        break;

    // 订单状态查询
    case 'order_status':
        if (!(Api::check($_GET, ['order_id']))) {
            Api::fail(Api::NO_MUST_PARAM);
            break;
        }

        $orderInfo = ct('tradelog')->fetch($order_id);

        // 订单不存在，订单已归还，前端跳走
        if (!$orderInfo || $orderInfo['uid'] != $uid || $orderInfo['status'] == ORDER_STATUS_RETURN) {
            Api::output(['status' => 7]);
            break;
        }
        // 借出时间超过2分钟，前端跳走
        if (time() - $orderInfo['borrow_time'] > 120) {
            Api::output(['status' => 7]);
            break;
        }
        // 订单未支付
        if ($orderInfo['status'] == ORDER_STATUS_WAIT_PAY) {
            Api::output(['status' => 0]);
            break;
        }
        // 订单已支付
        if ($orderInfo['status'] == ORDER_STATUS_PAID) {
            Api::output(['status' => 1]);
            break;
        }
        // 机器出伞中
        if ($orderInfo['status'] == ORDER_STATUS_RENT_CONFIRM_FIRST) {
            $message = unserialize($orderInfo['message']);
            Api::output(['status' => 3, 'slot' => $message['slot']]);
            break;
        }
        // 雨伞借出成功
        if ($orderInfo['status'] == ORDER_STATUS_RENT_CONFIRM) {
            Api::output(['status' => 2]);
            break;
        }
        // 用户未取走（含中间态）
        if (in_array($orderInfo['status'], [ORDER_STATUS_RENT_NOT_FETCH_INTERMEDIATE, ORDER_STATUS_RENT_NOT_FETCH])) {
            Api::output(['status' => 4]);
            break;
        }
        // 机器被人使用中导致借伞失败
        if ($orderInfo['status'] == ORDER_STATUS_LAST_ORDER_UNFINISHED) {
            Api::output(['status' => 6]);
            break;
        }
        // 其他情况导致机器出伞失败
        Api::output(['status' => 5]);
        break;

    // 记录用户form_id
    case 'form_id':
        if (!(Api::check($_GET, ['form_id']))) {
            Api::fail(Api::NO_MUST_PARAM);
            break;
        }
        $newFormId = json_decode($form_id, true);
        // count不符合规范的
        if (!is_int($newFormId['count'])) {
            Api::output();
            break;
        }
        // 清除模拟器上的传过来的假id
        if ($newFormId['form_id'] == 'the formId is a mock one') {
            Api::output();
            break;
        }

        $newFormId['timestamp'] = time();
        // 添加到weapp表中
        $form_ids   = ct('user_weapp')->fetch($uid)['form_ids'];
        $form_ids   = empty($form_ids) ? [] : json_decode($form_ids, 1);
        $form_ids[] = $newFormId;
        // 防止form id太多了text类型字段装不下～～
        while (count($form_ids) > 100) {
            array_shift($form_ids);
        }
        $form_ids   = json_encode($form_ids, 1);
        $ret        = ct('user_weapp')->update($uid, ['form_ids' => $form_ids]);
        Api::output();
        break;

    default:
        # code...
        break;

}

LOG::DEBUG('api response : ' . print_r(Api::getLogStr(), 1));
