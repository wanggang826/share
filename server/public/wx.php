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
$LOG_FILENAME = '_wx';
// 加载配置
require_once JJSAN_DIR_PATH . '/cfg.inc.php';

// 加载类库
require_once JJSAN_DIR_PATH . '/lib/scurl.class.php';
require_once JJSAN_DIR_PATH . '/lib/wxapi.class.php';
require_once JJSAN_DIR_PATH . '/lib/wxpay.class.php';
require_once JJSAN_DIR_PATH . '/lib/swapi.class.php';

// 加载业务函数
require_once JJSAN_DIR_PATH . '/func.inc.php';

use model\User;

if ($_GET['echostr']) {
    wxAPI::valid(true);
} else {
    if (wxAPI::valid()) {
        Log::WARN("WeiXin Msg Valid Succeed.");
        $data = $GLOBALS["HTTP_RAW_POST_DATA"] ?: file_get_contents("php://input");
        LOG::DEBUG(print_r($data, true));
        wxAPI::getMsg();
        $openId     = (string)wxAPI::$wxMsgData['client'];
        $user_model = new User();

        $user = ct('user')->where(['openid' => $openId])->first();

        // 只要不是取消关注事件，都可以获取用户信息
        if (wxAPI::$wxMsgData['event'] != 'unsubscribe') {

            if (empty($user)) {
                // 用户先使用小程序，后关注公众号的情况下
                // 先用unionid搜索一下
                $userInfo = callWeiXinFuncV2("wxAPI::getUserInfo", [$openId]);
                if ($userInfo['subscribe'] == 1 && $userInfo['unionid']) {
                    $rst = ct('user_info')->where(['unionid' => $userInfo['unionid']])->first();
                    // 有记录的话，说明是小程序用户
                    if ($rst) {
                        // 更新user表即可
                        ct('user')->update($rst['id'], [
                            'openid'      => $openId,
                            'platform'    => PLATFORM_WX,
                            'unsubscribe' => 0, //关注了
                            'create_time' => date('Y-m-d H:i:s'),
                        ]);
                        $user = ['id' => $rst['id'], 'openid' => $openId];
                        LOG::INFO('weapp user, id: ' . $rst['id'] . ' , openid: ' . $openId);
                    } else {
                        // 没有记录说明是公众号新用户
                        $userId = ct('user')->insert([
                            'openid'   => $openId,
                            'platform' => PLATFORM_WX,
                        ], true);

                        $user = ['id' => $userId, 'openid' => $openId];
                        LOG::INFO("new user, id: $userId , openid: $openId");

                        // 用户信息更新
                        $user_model->update_weixin_userinfo($userId, $openId);
                    }
                } else {
                    // @todo 暂时先退出。后面再说
                    echo 'success';
                    exit;
                }

            }
        }


        $uid = $user['id']; // 用户ID
        //================= 请求处理 ==============//
        switch (wxAPI::$wxMsgData['type']) {
            case 'event':
                switch (wxAPI::$wxMsgData['event']) {
                    // 关注事件
                    case 'subscribe':
                        // 新增微信用户事件
                        LOG::DEBUG('subscribe event, uid:' . $uid);
                        $user_model->user_subscribe($uid);

                        // 每个事件只能回复一次
                        // 带有站点参数的推送图文消息
                        // 其他情况回复欢迎语（不带有参数的、带其他参数等等）
                        $eventkey = wxAPI::$wxMsgData['eventkey'];
                        if (substr($eventkey, 0, 8) == 'qrscene_') {
                            $user_model->user_scan_log($user['id']);
                            $sceneId = substr($eventkey, 8);

                            // 场景ID即站点ID
                            // 场景id 1000以内待定, 1001以上绑定站点id
                            if (!ENV_DEV && $sceneId <= 1000) {
                                wxAPI::replyTextMsg('场景id未设定');
                                exit;
                            }
                            // 判断是否存在
                            $station = ct('station')->fetch($sceneId);
                            if (!$station) {
                                wxAPI::replyTextMsg('设备未激活');
                                exit;
                            }
                            // 运营环境需要绑定商铺站点才能使用
                            if (!ENV_DEV) {
                                $shopStation = ct('shop_station')->where(['station_id' => $sceneId])->first();
                                if (!$shopStation) {
                                    wxAPI::replyTextMsg('设备未绑定');
                                    exit;
                                }
                            }
                            // 检查设备是否在线, 若断线, 则直接回复用户提示, 若在线则返回借出图文
                            if (!swAPI::isStationOnline($sceneId)) {
                                LOG::DEBUG("station offline : $sceneId ");
                                wxAPI::replyTextMsg('非常抱歉，这台街借伞暂时无法连接网络，请稍后再试。或查看附近网点，前往附近的网点进行租借。');
                                exit;
                            }
                            $replyMsg[] = [
                                'title'       => '请点击“一键借伞”按钮',
                                'description' => "",
                                'picurl'      => 'https://mmbiz.qlogo.cn/mmbiz_jpg/xGVLLfIfSuqCvh2yR0dDBINiaZlhVHChTEC5YYxiaJElA2A9xfm1EYXibxRwscWWTDoYFVxOJFvaXL6ULApVJPPow/0?wx_fmt=jpeg',
                                //'url' => "http://" . SERVER_DOMAIN . "/wxpay.php?act=pay&mobile=2&stationid=$sceneId&itemtype=umbrella&t=" . time(),
                                'url'         => "//" . SERVER_DOMAIN . "/rent.php?stationid=$sceneId&t=" . time(),
                            ];
                            $pictext    = ct('shop_station')->getPicSettingsByStationId($sceneId);
                            if ($pictext) {
                                $now   = time();
                                $stime = $pictext['stime'];
                                $etime = $pictext['etime'];
                                if ($now >= $stime && $now <= $etime) {
                                    $msgInfo           = json_decode($pictext['pictext'], 1);
                                    $msgInfo['picurl'] = $msgInfo['wechat_picurl'];
                                    $replyMsg[]        = $msgInfo;
                                }
                            }
                            wxAPI::replyPicTextMsg($replyMsg);
                        } else {
                            LOG::INFO("subscribe eventkey: $eventkey");
                            $subscribeMsg = json_decode(C::t('common_setting')->fetch('jjsan_wechat_subscribeMsg'), true);
                            if (empty($subscribeMsg)) {
                                $msg  = "感谢关注JJ伞，我们致力于为您提供随街可借的雨伞！\n\n";
                                $msg .= "<a href='http://" . SERVER_DOMAIN . "/index.php?mod=wechat&act=user&opt=center#/useFlow'>了解JJ伞使用流程</a>";

                                $subscribeMsg = $msg;
                            }
                            wxAPI::replyTextMsg($subscribeMsg);
                        }
                        exit;

                    // 取消关注事件
                    case 'unsubscribe':
                        LOG::DEBUG('unsubscribe event, uid:' . $uid);
                        $user_model->user_unsubscribe($user['id']);
                        exit;

                    case 'scancode_push':
                        LOG::INFO("scancode_push eventkey: " . wxAPI::$wxMsgData['eventkey']);
                        exit;
                    // 扫码事件
                    case 'SCAN':
                        if (!isset(wxAPI::$wxMsgData['eventkey'])) {
                            // 回复默认信息
                            wxAPI::replyTextMsg(defaultWeiXinMsg());
                            exit;
                        }
                        $sceneId = (int)wxAPI::$wxMsgData['eventkey'];

                        // $sceneId == 1<<20 时为添加维护人员
                        if ($sceneId == INSTALL_MAN_ADD_SCENE_ID) {
                            $ret = addInstallMan($openId, $uid);
                            LOG::DEBUG('add station manager openid:' . $openId . ',user id:' . $uid);
                            if ($ret) {
                                LOG::DEBUG('add success');
                                wxAPI::replyTextMsg('您已申请添加为维护人员，请等待审核。');
                            } else {
                                LOG::DEBUG('add fail');
                                wxAPI::replyTextMsg('fail operation, please retry later.');
                            }
                            exit;
                        }

                        // 场景ID即站点ID
                        // 场景id 1000以内待定, 1001以上绑定站点id
                        if (!ENV_DEV && $sceneId <= 1000) {
                            wxAPI::replyTextMsg('场景id未设定');
                            exit;
                        }
                        //判断是否存在
                        $station = ct('station')->fetch($sceneId);
                        if (!$station) {
                            // 回复默认信息
                            wxAPI::replyTextMsg('设备未激活');
                            exit;
                        }
                        // 运营环境需要绑定商铺站点才能使用
                        //                        if (!ENV_DEV) {
                        //                            $shopStation = ct('shop_station')->where(['station_id' => $sceneId])->first();
                        //                            if (!$shopStation) {
                        //                                wxAPI::replyTextMsg('设备未绑定');
                        //                                exit;
                        //                            }
                        //                        }
                        // =========== 判断是否是安装维护人员, 若是, 则返回维护页面消息 ===========//
                        $installs = json_decode(C::t('common_setting')->fetch('jjsan_install_man'), true);
                        if ($installs[$uid]) {

                            $shop_station_id = ct('shop_station')->getIdByStaionId($station['id']);
                            $lbsid           = ct('shop_station')->getField($shop_station_id, 'lbsid');
                            // 没有lbsid说明未描点
                            if ($lbsid == 0) {
                                LOG::DEBUG('station init position here, openid' . $openId . ',user id:' . $uid);
                                $replyMsg[] = [
                                    'title'       => '初始化地理位置',
                                    'description' => '描述',
                                    'picurl'      => 'http://mmsns.qpic.cn/mmsns/hX1d1OhZWxv7pQJbrtosNDCENz4EfaPLW3wVCGbJwhH68sLw8icHXbA/0',
                                    // 需修改成动态网页授权验证
                                    'url'         => "https://open.weixin.qq.com/connect/oauth2/authorize?appid=" . AppID . "&redirect_uri=http%3a%2f%2f" . WX_AUTH_DOMAIN . urlencode("/index.php?mod=wechat&act=shop&opt=init_addr&stationid=$sceneId&t=" . time()) . "&response_type=code&scope=snsapi_base&state=123#wechat_redirect",
                                ];
                            } else {
                                LOG::DEBUG('station manager here, openid' . $openId . ',user id:' . $uid);
                                $replyMsg[] = [
                                    'title'       => '点击本消息进入维护界面',
                                    'description' => '站点维护',
                                    'picurl'      => 'https://mmbiz.qlogo.cn/mmbiz/hX1d1OhZWxvX8SkHadEtGDx0sghYlRDibU51icujNR0LH5UTJn36oh5iaO7grG6IkPSnJUL0n3xbl8IFoJYAAYD0A/0?wx_fmt=jpeg',
                                    // 网页授权登录, 防止恶意转发信息
                                    'url'         => "https://open.weixin.qq.com/connect/oauth2/authorize?appid=" . AppID . "&redirect_uri=http%3a%2f%2f" . WX_AUTH_DOMAIN . urlencode("/index.php?mod=wechat&act=shop&opt=manage_page&stationid=$sceneId&t=" . time()) . "&response_type=code&scope=snsapi_base&state=123#wechat_redirect",
                                ];
                            }
                            wxAPI::replyPicTextMsg($replyMsg);
                            exit;
                        }

                        $user_model->user_scan_log($user['id']);    // 记录借雨伞事件
                        // 检查设备是否在线, 若断线, 则直接回复用户提示, 若在线则返回借出图文
                        if (!swAPI::isStationOnline($sceneId)) {
                            LOG::DEBUG("station offline : $sceneId ");
                            wxAPI::replyTextMsg('非常抱歉，这台街借伞暂时无法连接网络，请稍后再试。或查看附近网点，前往附近的网点进行租借。');
                            exit;
                        }
                        $replyMsg[] = [
                            'title'       => '请点击“一键借伞”按钮',
                            'description' => "",
                            'picurl'      => 'https://mmbiz.qpic.cn/mmbiz_png/0shRicALAmH0HjURf2SfyRRZMmAbibnvWV6xLCbrNgWiaEg14x3EA6DdXPic4CB9wFEHJuOSxnvUQ9JOVAXfKAhh4g/0?wx_fmt=png',
                            'url'         => "//" . SERVER_DOMAIN . "/rent.php?stationid=$sceneId&t=" . time(),

                        ];
                        $pictext    = ct('shop_station')->getPicSettingsByStationId($sceneId);
                        if ($pictext) {
                            $now   = time();
                            $stime = $pictext['stime'];
                            $etime = $pictext['etime'];
                            if ($now >= $stime && $now <= $etime) {
                                $msgInfo           = json_decode($pictext['pictext'], 1);
                                $msgInfo['picurl'] = $msgInfo['wechat_picurl'];
                                $replyMsg[]        = $msgInfo;
                            }
                        }

                        wxAPI::replyPicTextMsg($replyMsg);
                        break;
                    case 'CLICK':
                        LOG::DEBUG('just click');
                        break;
                    case 'TEMPLATESENDJOBFINISH':
                        LOG::DEBUG('TEMPLATESENDJOBFINISH status: ' . wxAPI::$wxMsgData['status']);
                        break;
                    case 'LOCATION':
                        LOG::DEBUG('just access location');
                        break;
                    default:
                        break;
                }
                break;

            case 'text':
                // 测试使用 白名单功能 测试环境需要绑定才能使用个人用户中心和附近网点
                if (ENV_DEV) {
                    $msg = (string)wxAPI::$wxMsgData['content'];
                    LOG::DEBUG('check text value' . $msg);
                    if ($msg == 'bind jjsan') {
                        $whitelist   = json_decode(C::t('common_setting')->fetch('jjsan_whitelist'), true);
                        $whitelist[] = $uid;
                        if (!C::t('common_setting')->update('jjsan_whitelist', json_encode($whitelist))) {
                            LOG::ERROR("fail to add whitelist to db wx openid:" . $openId . ",user id:" . $uid);
                            wxAPI::replyTextMsg('server db error');
                            exit;
                        }
                        LOG::DEBUG('add new whitelist user wx openid: ' . $openId . ',user id:' . $uid);
                        wxAPI::replyTextMsg('ok, you can use the menu function');
                        exit;
                    }
                }

                $reply_time = ct('user')->fetch($uid)['reply_time'];
                if (time() - $reply_time < 6 * 3600) {
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
                wxAPI::replyTextMsg($msg);
                ct('user')->update($uid, ['reply_time' => time()]);
                break;

            case 'image':
            case 'voice':
            case 'video':
            case 'shortvideo':
            case 'location':
            case 'link':
            default:
                break;
        }
    } else {
        $data = $GLOBALS["HTTP_RAW_POST_DATA"] ?: file_get_contents("php://input");
        Log::WARN("WeiXin Msg Valid Failed." . $data);
        echo "success";
    }
}