<?php
define('DISABLEXSSCHECK', true);
define('PLUGIN_NAME', 'jjsan');
define('JJSAN_DIR_PATH', __DIR__ . '/../');
define('DZ_ROOT', JJSAN_DIR_PATH . '/../../');

// 初始化 discuz 内核对象
require DZ_ROOT . '/class/class_core.php';
$discuz = C::app();
$discuz->init();

// 加载基于PSR0/4规范的类
require_once JJSAN_DIR_PATH . '/vendor/autoload.php';

// $LOG_FILENAME配置log文件名称
$LOG_FILENAME = '_alipay';
// 加载配置
require_once JJSAN_DIR_PATH . '/cfg.inc.php';

// 加载类库
require_once JJSAN_DIR_PATH . '/lib/scurl.class.php';
require_once JJSAN_DIR_PATH . '/lib/swapi.class.php';
require_once JJSAN_DIR_PATH . '/lib/alipay/AlipayAPI.php';

// 加载业务函数
require_once JJSAN_DIR_PATH . '/func.inc.php';

use model\User;


// 日志记录下此次请求
$data = $GLOBALS["HTTP_RAW_POST_DATA"] ? : file_get_contents("php://input");
LOG::DEBUG ("GET: " . var_export ($_GET, true ));
LOG::DEBUG(var_export($data, true));

// 验证网关请求
if ($_GET["service"] == "alipay.service.check") {
	AlipayAPI::valid(true);
} else {
    // 处理芝麻信用通知
    if (isset($_GET['notify_type'])) {
        if (!AlipayAPI::verifyPayNotifyV2()) {
            echo 'verify fail';
            exit;
        }
        switch ($_GET['notify_type']) {

            # 创建信用订单
            case 'ORDER_CREATE_NOTIFY':
                zhimaCreateNotify($_GET['out_order_no'], $_GET['order_no']);
                break;

            #　订单完成(扣款需要查询)
            case 'ORDER_COMPLETE_NOTIFY':
                ct('trade_zhima')->update($_GET['out_order_no'],
                    [
                        'status' => ZHIMA_ORDER_QUERY_WAIT, //定时任务进行扣款成功确认
                        'update_time' => time()
                    ]
                );
                LOG::DEBUG('update zhima query status: ' . $_GET['out_order_no']);
                break;

            default:
        }
        echo 'success';
        LOG::DEBUG('echo success');
        exit;
    }

    // 验证签名
    if (!AlipayAPI::valid(false)) {
        Log::DEBUG("Alipay Msg Valid Failed." . $data);
        echo AlipayAPI::mkAckMsg();
        exit;
    }

    // 处理收到的消息
    Log::WARN("Alipay Msg Valid Succeed.");
    AlipayAPI::getMsg();    // 解析支付发送过来的数据
    $openId = (string)AlipayAPI::$msgData['client'];    // 用户唯一凭证openid
    //================= 账号管理 ==============/
    // 新增用户
    $user = ct('user')->fetch_by_field('openid', $openId);
    $user_model = new User();
    if (!$user) {
        $userId = ct('user')->insert(['openid' => $openId, 'platform' => PLATFORM_ALIPAY], true);
        $user = ['id' => $userId, 'openid' => $openId];
        LOG::DEBUG('new user, openid:' . $openId);
        ct('user_info')->insert(['id' => $userId, 'openid' => $openId, 'subscribe_time' => time()]);
    }

    // 根据type分别处理各种事件
    switch (AlipayAPI::$msgData['type']) {

        // 文本推送
        case 'text':
            if (ENV_DEV) {
                $msg = (string)AlipayAPI::$msgData['content'];
                LOG::DEBUG('check text value' . $msg);
                if($msg == 'bind jjsan') {
                    $whitelist = json_decode( C::t('common_setting')->fetch('jjsan_whitelist'), true );
                    $whitelist[] = $user['id'];
                    if(! C::t('common_setting')->update('jjsan_whitelist', json_encode($whitelist))) {
                        LOG::ERROR("fail to add whitelist to db alipay openid:" . $openId. ",user id:". $user['id']);
                        AlipayAPI::replyTextMsg( 'server db error' );
                        exit;
                    }
                    LOG::DEBUG('add new whitelist user alipay openid: ' . $openId . ',user id:' .$user['id']);
                    AlipayAPI::replyTextMsg( 'ok, you can use the menu function', 1 );
                    exit;
                }
            }
            $reply_time = ct('user')->fetch($uid)['reply_time'];
            if(time() - $reply_time < 6*3600){
                exit;
            }
            $msg = '';
            $msg .= "<a href='http://" . SERVER_DOMAIN . "/index.php?mod=wechat&act=user&opt=center#/useFlow'>了解JJ伞使用流程</a>\n\n";
            $msg .= "常见问题：\n\n";
            $msg .= "JJ伞如何收费？\n\n";
            $msg .= "扫码取伞时，伞没有弹出？\n\n";
            $msg .= "归还时提示归还失败？\n\n";
            $msg .= "伞柄卡入伞槽，没收到还伞成功提示？\n\n";
            $msg .= "<a href='http://" . SERVER_DOMAIN . "/index.php?mod=wechat&act=user&opt=center#/userHelp'>>>使用帮助<<</a>";
            AlipayAPI::replyTextMsg($msg);
            ct('user')->update($uid, ['reply_time' => time()]);
            exit;
            break;

        // 处理事件消息推送
        case 'event':
            switch (AlipayAPI::$msgData['event']) {

                // 关注事件
                case 'follow':
                    $user_model->user_subscribe($user['id']);
                    LOG::DEBUG('alipay follow openid: ' . $openId);
                    // 未关注的用户扫码二维码，会先推送enter事件。所以这里只需推送欢迎语就行了。
                    $subscribeMsg = json_decode( C::t('common_setting')->fetch('jjsan_wechat_subscribeMsg'), true );
                    if (empty($subscribeMsg)) {
                        $msg  = "感谢关注JJ伞，我们致力于为您提供随街可借的雨伞！\n\n";
                        $msg .= "<a href='http://" . SERVER_DOMAIN . "/index.php?mod=wechat&act=user&opt=center#/useFlow'>了解JJ伞使用流程</a>";
                        $subscribeMsg = $msg;
                    }
                    AlipayAPI::replyTextMsg($subscribeMsg);
                    break;

                // 取消关注事件
                case 'unfollow':
                    $user_model->user_unsubscribe($user['id']);
                    break;

                // 进入界面事件
                // 说明下: 支付宝和微信有些区别,微信有扫码事件, 支付宝就是进入事件(默认不订阅,需要设置)
                case 'enter':
                    if (!isset(AlipayAPI::$msgData['eventkey'])) {
                        break;
                    }

                    $sceneId = (int)AlipayAPI::$msgData['eventkey'];

                    // 场景id 1000以内待定, 1001以上绑定站点id
                    if (!ENV_DEV && $sceneId <= 1000) {
                        AlipayAPI::replyTextMsg('场景id未设定');
                        exit;
                    }


                    //判断是否存在
                    $station = ct('station')->fetch($sceneId);
                    if (!$station) {
                        // 回复默认信息
                        AlipayAPI::replyTextMsg('设备未激活');
                        exit;
                    }

                    // 运营环境需要绑定商铺站点才能使用
//                    if (!ENV_DEV) {
//                        $shopStation = ct('shop_station')->where(['station_id' => $sceneId])->first();
//                        if (!$shopStation) {
//                            AlipayAPI::replyTextMsg('设备未绑定');
//                            exit;
//                        }
//                    }

                    // 检查设备是否在线, 若断线, 则直接回复用户提示, 若在线则返回借出图文
                    if (!swAPI::isStationOnline($sceneId)) {
                        AlipayAPI::replyTextMsg('非常抱歉，这台街借伞暂时无法连接网络，请稍后再试。或查看附近网点，前往附近的网点进行租借。');
                        exit;
                    }
                    $replyMsg[] = [
                        'title' => '请点击“一键借伞”按钮',
                        'description' => "",
                        'picurl' => 'https://mmbiz.qpic.cn/mmbiz_png/0shRicALAmH0HjURf2SfyRRZMmAbibnvWV6xLCbrNgWiaEg14x3EA6DdXPic4CB9wFEHJuOSxnvUQ9JOVAXfKAhh4g/0?wx_fmt=png',
                        'url' => "//" . SERVER_DOMAIN . "/rent.php?stationid=$sceneId&t=" . time()

                    ];
                    $pictext = ct('shop_station')->getPicSettingsByStationId($sceneId);
                    if($pictext){
                        $now = time();
                        $stime = $pictext['stime'];
                        $etime = $pictext['etime'];
                        if($now >= $stime && $now <= $etime){
                            $msgInfo = json_decode($pictext['pictext'], 1);
                            $msgInfo['picurl'] = $msgInfo['alipay_picurl'];
                            $replyMsg[] = $msgInfo;
                        }
                    }
                    $user_model->user_scan_log($user['id']); // 记录借雨伞事件

                    AlipayAPI::replyPicTextMsg($replyMsg);
                    break;

                case 'click':
                    AlipayAPI::replyTextMsg("oops!");
                    break;

                case 'TEMPLATESENDJOBFINISH':
                    LOG::DEBUG('TEMPLATESENDJOBFINISH status: ' . AlipayAPI::$msgData['status']);
                    break;

                default:
                    break;
            }
            break;

        default:
            break;
    }
}
echo AlipayAPI::mkAckMsg();
exit;
