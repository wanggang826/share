<?php
// 若不是手机模式则需强制成手机模式, 不然会找不到模板
define('IN_MOBILE', 2);
if(IN_MOBILE == 1) {
	header("location: " . "{$_SERVER['PHP_SELF']}?{$_SERVER['QUERY_STRING']}&mobile=2");
	exit;
}

/**
 * 用户自动登录入口(微信/支付宝)
 * 拦截微信端或支付宝钱包浏览器发起的请求, 若拦截到则自动登录
 * 获取登录用户的uid, openid, platform, 并存于session中
 *
 * 本地测试环境保存uid到session
 * 线上测试环境保存openid到session
 * 线上运营环境保存openid到session
 */

/**
 * 现在这个文件只应用到维护人员页面了
 */

session_start();

// 测试环境
if (ENV_DEV) {

    // uid存在于url中, 通过session保存和获取uid
    // 这种情况适用于本地测试环境
    $uid = $uid ? : $_SESSION['uid'];
    if ($uid) {
        $_SESSION['uid'] = $uid;
        $_user = ct('user')->fetch($uid);
        $openid = $_user['openid'];
        $platform = $_user['platform'];
    } else {
        // 没有uid的话, 走正常通道获取openid
        // 这种情况适用于线上测试环境
        $platform = getPlatform();
        switch($platform) {

            # 微信平台
            case PLATFORM_WX:
                if (!$_SESSION['openid']) {
                    $jspay = new JsApiPay();
                    $openid = $jspay->GetOpenid();
                } else {
                    $openid = $_SESSION['openid'];
                }
                break;

            # 支付宝平台
            case PLATFORM_ALIPAY:
                if (!$_SESSION['openid']) {
                    require_once JJSAN_DIR_PATH  . 'lib/alipay/AlipayAPI.php';
                    $openid = AlipayAPI::getOpenid('auth_user');
                } else {
                    $openid = $_SESSION['openid'];
                }
                break;

            case PLATFORM_NO_SUPPORT:
            default:
                include template("jjsan:touch/platform_prompt");
                exit;
        }
        $_SESSION['openid'] = $openid;
        $_user = ct('user')->where(['openid' => $openid])->first();
        $uid = $_user['id'];
    }

} else {
    // 运营环境,保存openid到session中
    $platform = getPlatform();
    switch($platform) {

        # 微信平台
        case PLATFORM_WX:
            if (!$_SESSION['openid']) {
                $jspay = new JsApiPay();
                $openid = $jspay->GetOpenid();
            } else {
                $openid = $_SESSION['openid'];
            }
            break;

        # 支付宝平台
        case PLATFORM_ALIPAY:
            if (!$_SESSION['openid']) {
                require_once JJSAN_DIR_PATH  . 'lib/alipay/AlipayAPI.php';
                $openid = AlipayAPI::getOpenid('auth_user');
            } else {
                $openid = $_SESSION['openid'];
            }
            break;

        case PLATFORM_NO_SUPPORT:
        default:
            include template("jjsan:touch/platform_prompt");
            exit;
    }
    $_user = ct('user')->where(['openid' => $openid])->first();
    $uid = $_user['id'];
    $_SESSION['openid'] = $openid;
}

// 没有openid, uid
if (!$uid || !$openid) {
    LOG::WARN('ip address: ' . $_SERVER['REMOTE_ADDR']);
    include template("jjsan:touch/platform_prompt");
    exit;
}

LOG::DEBUG("user entry uid: $uid, platform: $platform");
LOG::DEBUG("user entry params: " . json_encode($_GET));