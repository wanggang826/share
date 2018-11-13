<?php
// 全部跳到用户中心
header("location:index.php?mod=wechat&act=user&opt=center#/oneKeyUse");
exit;
define('DISABLEXSSCHECK', true);
define('PLUGIN_NAME', 'jjsan');
define('JJSAN_DIR_PATH', __DIR__ . '/../');
define('DZ_ROOT', JJSAN_DIR_PATH . '/../../');

// $LOG_FILENAME配置log文件名称
$LOG_FILENAME = '_wxpay';
// 加载配置
require_once JJSAN_DIR_PATH . '/cfg.inc.php';

try {
    // 初始化 discuz 内核对象
    require DZ_ROOT . '/class/class_core.php';
    $discuz = C::app();
    $discuz->init();
} catch (DbException $e) {
    // 处理db类异常
    LOG::ERROR('DBException: db server error!!!!, error msg: ' . $e->getMessage());
    header("HTTP/1.0 500 Server Error");
    include template('jjsan:common/system_error');
    exit;
} catch (Exception $e) {
    // 其他异常暂不处理
    LOG::ERROR('Exception: server error!!!!, error msg: ' . $e->getMessage());
}

// 加载基于PSR0/4规范的类
require_once JJSAN_DIR_PATH . '/vendor/autoload.php';

// 加载类库
require_once JJSAN_DIR_PATH . '/lib/scurl.class.php';
require_once JJSAN_DIR_PATH . '/lib/wxapi.class.php';
require_once JJSAN_DIR_PATH . '/lib/wxpay.class.php';
require_once JJSAN_DIR_PATH . '/lib/swapi.class.php';


// 加载业务函数
require_once JJSAN_DIR_PATH . '/func.inc.php';

// $_GET数据过滤
$_GET = array_map(function($v){
    return is_array($v) ? $v : trim($v);
}, $_GET);
extract($_GET);

require_once JJSAN_DIR_PATH . '/mod/user_check.inc.php';

use model\User;
use model\Api;

// 支付平台判断，强制使用芝麻信用
if ($isZhima) {
    $platform = PLATFORM_ZHIMA;
    $isZhima = true;
}

switch ($act) {

    # 支付页面, 微信扫码后点击借, 跳转的页面
    case 'pay':
        // 检查站点是否在线
        if (!swAPI::isStationOnline($stationid)) {
            include template('jjsan:wxpay/device_offline');
            exit;
        }
        switch ($_GET['itemtype']) {
            case 'umbrella':
                $curTag = TAG_UMBRELLA;
                break;
            default:
                $curTag = 0;
        }
        $navtitle = '街借伞支付押金';

        // tag对应的商品名称 @todo
        $menuInfo = ct('menu')->where(['tag' => $curTag])->first();

        // 获取当前站点下需要需要租赁的物品信息
        $shopStationInfo = ct('shop_station')->where(['station_id' => $stationid])->first();
        // 当前站点的收费配置
        $feeSettings = ct('fee_strategy')->getStrategySettings($shopStationInfo['fee_settings']);

        // 当前用户的可用余额
        $usablemoney = ct('user')->getField($uid, 'usablemoney') + 0;
        // 当前站点可租雨伞数量
        $stationInfo = ct('station')->fetch($stationid);
        $umbrellaNumbers = $stationInfo['usable'];
        // 预留雨伞颜色,种类


        // 进入该页面事件
        $u = new User();
        $u->user_in_shop_page($uid);

        $price = $menuInfo['price']; //租赁价格
        if ($usablemoney == 0) {
            // 全额支付
            $diffPage = 1;
        } else {
            if( $price > $usablemoney) {
                // 补款
                $diffPage = 2;
                $payMore = $price - $usablemoney;
            } else {
                // 直接借
                $diffPage = 3;
            }
        }
        $feeStr = makeFeeStr($feeSettings);
        include template('jjsan:wxpay/pay');
        break;

    # 支付ajax请求页面
    case 'paydirect':
        LOG::DEBUG('start umbrella rent process, openid: ' . $openid . ' ,stationid:' . $stationid . ' ,user id:' . $uid);
        // 记录下点击确认支付事件
        $user = new User();
        $user->user_pay_event($uid);

        // 机器当前是否借出操作中
        if(ct('tradelog')->hasBorrowingOrder($stationid)) {
            echo json_encode(makeErrorData(1, "station is busy"));
            exit;
        }

        // 用户是否有完成的芝麻信用订单
        if ($platform == PLATFORM_ZHIMA && ct('tradelog')->hasUnfinishedZhimaOrder($uid)) {
            echo json_encode(makeErrorData(2, "user has unfinished zhima order"));
            exit;
        }

        // 商品信息
        $itemid = $_GET['itemid'];
        $menu = ct('menu')->fetch($itemid);
        $tag = $menu['tag'];
        $price = $menu['price'] * $menu['discount'];
        $totalsave = $menu['price'] - $price;
        $amount = 1;
        $flavor = $_GET['flavor'];
        $totalprice = round($price * $amount, 2);
        $usableMoney = ct('user')->getField($uid, 'usablemoney');
        $usbaleMoney = round($usableMoney, 2);
        LOG::DEBUG("user: $uid, usable: $usableMoney, totalprice: $totalprice");

        // 若用户可用余额>=最少可允许的押金数额, 则将押金设置成用户的可用余额即可
        if ($tag == TAG_UMBRELLA) {
            // @todo 暂时移除
        }

        $message = unserialize($menu['message']);

        $date = getdate(time());
        $year = $date['year'];
        $mon = $date['mon'];
        $mday = $date['mday'];
        $h = $date['hours'];
        $m = $date['minutes'];
        $s = $date['seconds'];
        $sn = rand(1, 99999);

        $orderid = sprintf("JJSAN-%u%02u%02u-%02u%02u%02u-%05u", $year, $mon, $mday, $h, $m, $s, $sn);
        LOG::DEBUG("openid: $openid, orderid: $orderid");

        $stationInfo = ct('station')->fetch($stationid);
        $shopStationInfo = ct('shop_station')->where(['station_id' => $stationid])->first();
        $shopInfo = ct('shop')->fetch($shopStationInfo['shopid']);
        $title = '';
        if($shopInfo['name']){
            $title = $shopInfo['name'];
        } elseif ($shopStationInfo['title']){
            $title = $shopStationInfo['title'];
        } else {
            $title = $stationInfo['title'];
        }

        // message 中目前存2个信息 slot和img
        $ret = ct('tradelog')->insert([
            'orderid'                => $orderid,
            'price'                  => $totalprice,
            'baseprice'              => $totalsave,
            'openid'                 => $openid,
            'status'                 => ORDER_STATUS_WAIT_PAY,
            'message'                => '',
            'lastupdate'             => time(),
            'borrow_station'         => $stationid,
            'borrow_station_name'    => $title ? : DEFAULT_STATION_NAME,
            'borrow_shop_id'         => $shopStationInfo['shopid'],
            'borrow_city'            => $shopInfo['city'],
            'borrow_device_ver'      => $stationInfo['device_ver'],
            'borrow_shop_station_id' => $shopStationInfo['id'],
            'tag'                    => $tag,
            'uid'                    => $uid,
            'shop_type'              => $shopInfo['type'],
            'seller_mode'            => 0, //直营或者代理 默认直营
            'refundno'               => $isZhima ? ORDER_ZHIMA_NOT_REFUND : 0, //芝麻信用订单不能用于退款
            'platform'               => $platform,
            // 借出时间是从终端获取的，这里写入时间只是为了方便一些异常订单显示页面中有时间这个参数
            'borrow_time'            => time(),
        ], false, false, true);

        if (!$ret) {
            echo json_encode(makeErrorData(-2, '服务器内部错误，请重试！'));
            exit;
        }

        //保存收费策略
        $feeSettings = ct('fee_strategy')->getStrategySettings($shopStationInfo['fee_settings']);
        ct('tradeinfo')->insert(['orderid' => $orderid, 'fee_strategy' => json_encode($feeSettings)]);
        LOG::DEBUG("save order id $orderid , fee strategy " . print_r($feeSettings, 1));
        LOG::DEBUG('orderid: ' . $orderid . ' total price: ' . $totalprice);

        // 雨伞支付押金
        if ($tag == TAG_UMBRELLA) {
            // 非芝麻信用订单
            if (!$isZhima) {
                LOG::DEBUG('begin to pay in user account');
                $payRet = ct('user')->pay($uid, $totalprice);
                // 账户类押金支付
                if ($payRet) {
                    $ret = ct('tradelog')->update($orderid, ['status' => ORDER_STATUS_PAID, 'refundno' => ORDER_NOT_REFUND, 'lastupdate' => time()]);
                    if (!$ret) {
                        LOG::ERROR("pay direct success, but status update fail. orderid:" . $orderid);
                        exit;
                    } else {
                        LOG::DEBUG("pay direct success, status update success, orderid:" . $orderid);
                    }
                    // 出伞命令
                    swAPI::borrowUmbrella($stationid, $orderid);
                    LOG::DEBUG('pay in account successfully');
                    echo json_encode(['errcode' => 0, 'paytype' => 1, 'orderid' => $orderid]);
                    exit;
                }

                // 押金不足,需要在线支付
                if($totalprice > $usableMoney) {
                    $totalprice = $totalprice - $usableMoney;
                    LOG::DEBUG('user need pay money online: ' . $totalprice);
                }
            } else {
                LOG::DEBUG("zhima order");
            }

            switch ($platform) {

                # 微信支付
                case PLATFORM_WX:
                    $tools = new JsApiPay();
                    $input = new WxPayDataBase();
                    $input->values = [
                        'body'         => $menu['subject'],
                        'attach'       => "Attach",
                        'out_trade_no' => $orderid,
                        'total_fee'    => round(($totalprice * 100)), //单位是分, 四舍五入
                        'time_start'   => date("YmdHis"),
                        'time_expire'  => date("YmdHis", time() + 600),
                        'goods_tag'    => "NOTAG",
                        'notify_url'   => "http://" . SERVER_DOMAIN . "/wxpaynotify.php",
                        'trade_type'   => "JSAPI",
                        'openid'       => $openid,
                    ];
                    $order = WxPayApi::unifiedOrder($input);
                    $jsApiParameters = $tools->GetJsApiParameters($order);

                    $debug = str_replace(',', "\n", $jsApiParameters);
                    LOG::DEBUG('wxpay jsapi parameters:' . print_r($debug, 1));
                    echo json_encode(['errcode' => 0, 'paytype' => 0, 'jsApiParameters' => $jsApiParameters, 'orderid' => $orderid]);
                    break;

                #　支付宝支付
                case PLATFORM_ALIPAY:
                    $requsetParams = [
                        'body'           => $menu['subject'],
                        'subject'           => $menu['subject'],
                        'out_trade_no'   => $orderid,
                        'timeout_express' => '10m', // 非必填项
                        'total_amount'   => $totalprice, // 单位元, 精确到小数点后两位
                        //"seller_id"      => ALIPAY_SELLER_ID, // 非必填项 收款支付宝用户ID。 如果该值为空，则默认为商户签约账号对应的支付宝用户ID
                        //"product_code" => 'QUICK_WAP_PAY',
                        //"goods_type" => 1, // 非必填项, 0 是虚拟物品, 1 是实物
                        'return_url' => '/index.php?mod=wechat&act=user&opt=real_time&orderid='.$orderid, // 这个是公共请求参数里面的参数， 只是为了方便才放到这里来的
                    ];

                    //建立请求
                    require_once JJSAN_DIR_PATH . 'lib/alipay/AlipayAPI.php';
                    $formText = AlipayAPI::buildAlipaySubmitFormV2($requsetParams);
                    echo json_encode(['errcode' => 0, 'paytype' => 0, 'jsApiParameters' => $formText, 'orderid' => $orderid]);
                    exit;

                # 芝麻信用
                case PLATFORM_ZHIMA:
                    $feeInfo = getFeeInfoForZhima($stationid, $totalprice);
                    $params = [
                        "invoke_type"       => 'WINDOWS',
                        "invoke_return_url" => "http://" . SERVER_DOMAIN . '/zhimanotify.php',
                        "out_order_no"      => $orderid,
                        "product_code"      => "w1010100000000002858", // 信用借还产品码（固定值）
                        "goods_name"        => $menu['subject'],
                        "rent_info"         => $feeInfo[2],
                        "rent_unit"         => $feeInfo[1],
                        "rent_amount"       => $feeInfo[0] ? $feeInfo[0] : 0, // 单位元, 精确到小数点后两位
                        "deposit_amount"    => $totalprice, // 单位元, 精确到小数点后两位
                        "deposit_state"     => 'Y', // 单位元, 精确到小数点后两位
                        "expiry_time"       => $feeInfo[3],
                        "borrow_shop_name"  => $stationInfo['title'] ? : DEFAULT_STATION_NAME,
                    ];

                    //建立请求
                    require_once JJSAN_DIR_PATH . '/lib/alipay/AlipayAPI.php';
                    $formText = AlipayAPI::buildZhimaRentOrderSubmitForm($params);
                    LOG::DEBUG('zhima formText are : ' .print_r($formText, 1));
                    echo json_encode(['errcode' => 0, 'paytype' => 0, 'jsApiParameters' => $formText, 'orderid' => $orderid, 'zm' => true]);
                    exit;

                # 其他平台暂不支持
                default:
                    echo json_encode(makeErrorData(-3, 'unsupport platform'));

            }

        }
        exit;

    default:
		include template('jjsan:wxpay/device_offline');
}
