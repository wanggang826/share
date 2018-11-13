<?php
// 环境配置，可覆盖后面面的define公共配置，放在最前面
if(file_exists(JJSAN_DIR_PATH . "env_cfg.inc.php"))
	require_once JJSAN_DIR_PATH. "env_cfg.inc.php";

require_once JJSAN_DIR_PATH . 'error_define.cfg.php'; // 错误码定义

// LOG配置
require_once JJSAN_DIR_PATH . "/lib/log.class.php";
$LOG_FILENAME = (isset($LOG_FILENAME)) ? $LOG_FILENAME : '';
$logHandler = new CLogFileHandler(JJSAN_DIR_PATH . "/logs/" . date('Y-m-d') . $LOG_FILENAME . '.php');
Log::Init($logHandler, 15);


/////////////////////////////////////////////////////////////
/******************以下常量运营环境需要重新配置******************/

define( "SERVERIP", "127.0.0.1" );
define( "SERVER_DOMAIN", "jjs.lystrong.cn");
define( "WX_AUTH_DOMAIN", "jjs.lystrong.cn");
define("ENV_DEV", true); // false为运营环境, true为测试环境


define("SWOOLE_SERVER_IP", "127.0.0.1");
define("SWOOLE_SERVER_INTERNET_IP", "119.23.133.207");
define("SWOOLE_SERVER_PORT", "8888");

define("PAY_PLUGIN", "jjsan");
define("DEFAULT_TITLE", "街借伞");
define("DEFAULT_STATION_NAME", "JJ伞网点");

// 基础配置
define( "ROOT", "http://" . SERVER_DOMAIN . "/" );
define( "APPROOT", "/" );
define( "DZSTATICURL", "/static/dz/");
define( "ATTACHPATH", "./data/attachment/forum/" );
define( "DATA_DIR", JJSAN_DIR_PATH . '/data/');
define( "MINI_DATA", JJSAN_DIR_PATH . "/public/static/mini/");

// 微信测试环境配置
define('TOKEN', 'jjsanisverygood');
define('EncodingAESKey', 'hhq4t88euPTVhCZZTCcJ7Z9zBrPG89yKHbfLBjSLfsy');
define('AppID', 'wx471e3a3d6a962873');
define('AppSecret', '3eb21651e5d0652fff75095d66d5dd4c');
define('MchID', '1453776002');
define('PayKey', '111112222233333444445555566jjsan');
define('WXPAY_SSLCERT_PATH', JJSAN_DIR_PATH . '/lib/wxpaycert_old/apiclient_cert.pem');
define('WXPAY_SSLKEY_PATH', JJSAN_DIR_PATH . '/lib/wxpaycert_old/apiclient_key.pem');
define('WXPAY_CURL_PROXY_HOST', "0.0.0.0");
define('WXPAY_CURL_PROXY_PORT', 0);
define('WXPAY_REPORT_LEVENL', 1);

// 微信模板消息ID
define("WX_TEMPLATE_BROKEN_REMIND", "0FH7EznulBEgX72sqWPeU4P27LAmmmb2aJkd5VLozZs");	//雨伞损坏提醒

define("WX_TEMPLATE_RETURN_REMIND",     "g_34_4CmmTqsrnEyDHe8dyeejrRN09rLD6mJpHN_kSQ");	//归还提醒
define("WX_TEMPLATE_UMBRELLA_BORROW",   "OD_WwcYbIvSCfKZoMWLMUihKZB4BZhj4uJ1IK4raaqA"); //租借成功通知
define("WX_TEMPLATE_BORROW_FAIL",       "lMA1eWtW9A1TFLnLQWUCGg_f0axuHwdCnotWEHwzS2I"); //租借失败通知
define("WX_TEMPLATE_UMBRELLA_RETURN",   "QZxnVnWPLY3A4UvKcCwNtegrwny4prXXimJAq2H-owc"); //归还成功通知
define("WX_TEMPLATE_WITHDRAW_APPLY",    "1oXG5TBrFUyoDlBMWC7iuJP-1C9wrNerfypq6ZhBTXc"); //提现申请通知
define("WX_TEMPLATE_REFUND_FEE",        "YI75GERSXvujREYGJeOXWcsgmyKeNbEkDeWUYNRD_U4"); //费用退还通知
define("WX_TEMPLATE_LOSE_UMBRELLA",     "GUJgdd99C9jlBmpD_luADFvxN0ZZA7PsjkTaR2GxK7o"); //雨伞遗失通知

// 支付宝模板消息ID
define("ALIPAY_TEMPLATE_RETURN_REMIND",     "d0b3577423bf4adda63e1dfd29462835"); //归还提醒
define("ALIPAY_TEMPLATE_UMBRELLA_BORROW",   "43b96fc94fb542c1a982c729437016ec"); //租借成功通知
define("ALIPAY_TEMPLATE_BORROW_FAIL",       "7d09a585c35d4b129ea502a7fe2012c3"); //租借失败通知
define("ALIPAY_TEMPLATE_UMBRELLA_RETURN",   "4f76bf85242a49a99d463b8e98aab4ea"); //归还成功通知
define("ALIPAY_TEMPLATE_WITHDRAW_APPLY",    "01180de7f4c14a3fa52b43715d96fa2e"); //提现申请通知
define("ALIPAY_TEMPLATE_REFUND_FEE",        "a3be1d4ff01b454e9816211e8672b5f6"); //费用退还通知
define("ALIPAY_TEMPLATE_LOSE_UMBRELLA",     "0ff612b3740b4fb68c5a9609b823a50e"); //雨伞遗失通知


// 支付宝配置
define('ALIPAY_APPID', '2017033106499516');
// 网关(固定)
define('ALIPAY_GATEWAY', 'https://openapi.alipay.com/gateway.do');
// 支付宝公钥,上传app的公钥后会自动生成
define('ALIPAY_WINDOW_PUBLIC_KEY_FILE', JJSAN_DIR_PATH . 'lib/alipaykey/alipay_public_key.pem');
// app的私钥
define('ALIPAY_MERCHANT_PRIVATE_KEY_FILE', JJSAN_DIR_PATH . 'lib/alipaykey/app_private_key.pem');
// app的公钥
define('ALIPAY_MERCHANT_PUBLIC_KEY_FILE', JJSAN_DIR_PATH . 'lib/alipaykey/app_public_key.pem');


//签名方式
define('ALIPAY_SIGN_TYPE', 'RSA2');
//字符编码格式 目前支持utf-8
define('ALIPAY_INPUT_CHARSET', 'utf-8');
//ca证书路径地址，用于curl中ssl校验
//请保证cacert.pem文件在当前文件夹目录中
define('ALIPAY_CACERT', ''); // http无需这个
//访问模式,根据自己的服务器是否支持ssl访问，若支持请选择https；若不支持请选择http
define('ALIPAY_TRANSPORT', 'http');
// 支付类型 ，无需修改
define('ALIPAY_PAYMENT_TYPE', "1");
define('ALIPAY_PAY_METHOD', "alipay.trade.wap.pay");
define('ALIPAY_PAY_ASYNC_NOTIFY_URL', ROOT . 'alipaynotify.php');


// 百度地图
define( "BAIDU_MAP_AK", 'RA2LUlc0LrFtm5SooIgGqh2bCTMGSmo0'); //服务器端
define( "BAIDU_MAP_JS_AK", '3hQER1VQK7rkHG80IZ5MYRaptLxuZRMx'); //浏览器端
define( "GEOTABLE_ID", 166391 );

// 高德地图 注意:这个是Web端API
define( "GAODE_MAP_KEY", '0cee743a9151aecfbf8b9c4b3149e10c');
// 高德地图 注意:这个是Web服务类API
define( "GAODE_MAP_KEY_FOR_API", '261ed9998ca0263337a856303d40d9bf');


// 微信小程序
define("WEAPP_APP_ID", "wxce310daa3d1843ce");
define("WEAPP_APP_SECRET", "05e95f5aac3588f981ea71945ba339ad");

// 微信小程序模板消息ID
define("WEAPP_TEMPLATE_RETURN_REMIND",     "I1fvw-_cF6GKmA79KiyvtBy3BRGKyEHD2HTgQ1EZrSM"); //归还提醒
define("WEAPP_TEMPLATE_BORROW_SUCCESS",    "q6RzqOOToygwJfeCawzCs2YHhirI7B4bQCE1sb22y2Q"); //租借成功通知
define("WEAPP_TEMPLATE_BORROW_FAIL",       "sBrDnulBk2RbCNFv-EvG5f1OFVSh6ZoS0YMBhV07Trk"); //租借失败通知
define("WEAPP_TEMPLATE_UMBRELLA_RETURN",   "a9ZEHVI88QpwW4TixVPlQ8AlQY4CD-RIpKQVyAAxrHs"); //归还成功通知
define("WEAPP_TEMPLATE_WITHDRAW_APPLY",    "W5TYOlHoPQwuRX3lm7ade5m5D51lEazpSAcoCzJkjxU"); //提现申请通知
define("WEAPP_TEMPLATE_REFUND_FEE",        "NGwBs-NmRpcOJ_bMJFi99fw9A6vqsR1jItIrYcJ2WS4"); //费用退还通知
define("WEAPP_TEMPLATE_LOSE_UMBRELLA",     "q8wiSegHlbxd6qVnnFpUUSlwpGSfW1FX_9isVxxo9zA"); //雨伞遗失通知

/******************以上常量运营环境需要重新配置******************/
/////////////////////////////////////////////////////////////

// event key of scan
define("WX_SCAN_EVENT_SCAN" , 0);
define("WX_SCAN_EVENT_BORROW" , 1);
define("WX_SCAN_EVENT_BUY", 2);
define("WX_SCAN_EVENT_BIND" , 3);

define( 'WX_REQUEST_RETRY_COUNT', 2 ); // 当微信接口访问返回错误码时,更新access token进行重试的次数

// =============================================================================

define( "QRCODE_STATUS_NOT_ACTIVE", 0 );
define( "QRCODE_STATUS_WAIT_REVIEW", 1 );
define( "QRCODE_STATUS_APPROVED", 2 );
define( "QRCODE_STATUS_DENIED", 3 );
define( "QRCODE_STATUS_AUTO_APPROVED", 4 );
define( "QRCODE_LIMIT_TIME", 300 );

//=========== 订单字段refundno的取值 ===========//
define( "ORDER_NOT_REFUND", -1 ); //直接账户内支付,无法微信退款
define( "ORDER_ALL_REFUNDED", -2 ); //订单已全部退完
define( "ORDER_ZHIMA_NOT_REFUND", -3 ); //芝麻信用订单, 不能用于退款
//===========================================//

//==============以下状态均为支付状态==============//
define( "ORDER_STATUS_WAIT_PAY", 0 ); //订单未支付
define( "ORDER_STATUS_PAID", 1 ); //支付成功(微信,支付宝等)
define( "ORDER_STATUS_RENT_CONFIRM", 2 ); //设备端确认借出
define( "ORDER_STATUS_RETURN", 3 ); //归还状态
define( "ORDER_STATUS_RENT_CONFIRM_FIRST", 5 ); //第一次确认，需等待第二次确认
define( "ORDER_STATUS_RETURN_MANUALLY", 7 );  // 借出失败, 管理员后台手动撤销订单退回押金

define( "ORDER_STATUS_NETWORK_NO_RESPONSE", 64 ); //终端响应:网络超时
define( "ORDER_STATUS_LAST_ORDER_UNFINISHED", 65 ); //上一个订单未完成
define( "ORDER_STATUS_SYNC_TIME_FAIL", 66 ); //同步时间异常
define( "ORDER_STATUS_POWER_LOW", 67 ); //终端电量不足
define( "ORDER_STATUS_MOTOR_ERROR", 68 ); //终端借出时发生故障
define( "ORDER_STATUS_NO_UMBRELLA", 70 ); //终端没有合适的雨伞(没有伞,或者伞所在槽位被锁)
define( "ORDER_STATUS_TIMEOUT_NOT_RETURN", 92 );// 租金已扣完, 用户没有归还
define( "ORDER_STATUS_RETURN_EXCEPTION_MANUALLY_REFUND", 93 ); // 归还失败, 管理员手动退押金
define( "ORDER_STATUS_RETURN_EXCEPTION_SYS_REFUND", 94 ); // 归还失败, 雨伞状态异常(借出后同步), 系统自动归还退款
define( "ORDER_STATUS_TIMEOUT_REFUND", 96 ); //超时自动退款
define( "ORDER_STATUS_RENT_NOT_FETCH", 97 ); // 借出未拿走
define( "ORDER_STATUS_TIMEOUT_CANT_RETURN", 98 );// 租金已扣完, 用户已经归还
define( "ORDER_STATUS_RENT_NOT_FETCH_INTERMEDIATE", 99 ); // 借出未拿走(中间态)
define( "ORDER_STATUS_LOSS", 100 ); // 用户登记遗失

//===== 异常支付:支付金额+余额<应支付总额 ====//
define( "ORDER_STATUS_PAID_NOT_ENOUGH_EXCEPTION", 102 );

//======== 订单显示类型，仅作后台显示用途 =====//
define( "ORDER_LIST_ALL_BORROW", 1 ); //借出(包括借出未归还和已归还)
define( "ORDER_LIST_NOT_RETURN", 2 ); //借出未归还
define( "ORDER_LIST_RETURNED", 3 ); //已归还
define( "ORDER_LIST_EXCEPTION", 4 ); //异常
//=======================================//

// 退款记录状态
define( "REFUND_STATUS_REQUEST", 1 ); //退款申请
define( "REFUND_STATUS_DONE", 2 ); //退款完成

define( "RECORD_LIMIT_PER_PAGE", 10 );

define( "ITEMFORUMID", 2 );

define( "ORG_DISC_RATIO",  1 );
define( "ORG_SOLD_RATE",  0 );
define( "DISC_TUNE_LEVEL",  0.5 );
define( "PRICE_UPDATE_INTERVAL", 600 );

define('TAG_UMBRELLA', 1);

define( "INSTALL_MAN_ADD_SCENE_ID", 1 << 20 );

define('CUSTOMER_SERVICE_PHONE', '400-900-8113');



//=========== PLATFORM ==============//
define("PLATFORM_NO_SUPPORT", -1);
define("PLATFORM_WX", 0);
define("PLATFORM_ALIPAY", 1);
define("PLATFORM_ZHIMA", 2);
define("PLATFORM_WEAPP", 3);

// umbrella status for inside or outside of station
define("UMBRELLA_INSIDE", 0);
define("UMBRELLA_OUTSIDE", 1);
define("UMBRELLA_OUTSIDE_SYNC", 2); // 借出状态下却被同步, 属于异常状态但可归还
define("UMBRELLA_LOSS", 3); // 雨伞已遗失


// data module status
define("ALL_ORDER", 1);     // 总租借
define("ALL_USEFEE", 2);    // 总收入
define("SUCCESS_ORDER", 3); // 成功次数
define("FREE_ORDER", 4);    // 免费订单
define("CHARGE_ORDER", 5);  // 收费订单

define("BORROW_SUCCESS_ORDER", 1);
define("RETURN_SUCCESS_ORDER", 2);
define("BORROW_SUCCESS_ORDER_RATE", 3);
define("RETURN_SUCCESS_ORDER_RATE", 4);
// 芝麻信用订单状态
// 1. 先创建
// 2. 归还后进入结算
// 3. 结算后去查询扣款是否成功
// 4. 扣款结算成功, 订单完结
// 5. 等待取消
// 6. 取消成功
// 7. 芝麻订单结算扣款失败, 等待查询重试, 重试时间间隔较长
// 若出现负面记录需要撤销, 需联系芝麻信用小二处理
// 人工退款, 需要进入商户后台查询订单进行退款
define("ZHIMA_ORDER_CREATE", 1);
define("ZHIMA_ORDER_COMPLETE_WAIT", 2);
define("ZHIMA_ORDER_QUERY_WAIT", 3);
define("ZHIMA_ORDER_COMPLETE_SUCCESS", 4);
define("ZHIMA_ORDER_CANCEL_WAIT", 5);
define("ZHIMA_ORDER_CANCEL_SUCCESS", 6);
define("ZHIMA_ORDER_PAY_FAIL_QUERY_RETRY", 7);

// 后台登录相关
define("SUPER_ADMINISTRATOR_ROLE_ID", 1);

define("ADMIN_SESSION_EXPIRED_TIME", 60*60);
define("ADMIN_LOGIN_ERROR_NUMBER", 10);
define("ADMIN_USER_STATUS_DELETED", -1);
define("ADMIN_USER_STATUS_APPLIED", 0);
define("ADMIN_USER_STATUS_NORMAL", 1);
define("ADMIN_USER_STATUS_LOCKED", 2);
define("ADMIN_USER_STATUS_REFUSE", 3);

define("ADMIN_CITY_STATUS_APPLIED", 0);
define("ADMIN_CITY_STATUS_NORMAL",  1);

define("DEFAULT_BIG_HEARTBEAT", 3600); // 心跳频率

define("UMBRELLA_DEPOSIT_DIFF", 10); // 押金差额, 最少的允许押金=默认押金-押金差额, 则若低于允许的最少押金, 需重新补足押金至默认押金


define("STATION_CHECK_UPDATE_DELAY", 60*60); // 默认机器检查更新时间
define("STATION_HEARTBEAT", 180); // 心跳频率, 单位秒

// 终端升级程序路径
define("SOFT_FILE_PATH", JJSAN_DIR_PATH .  'data/update/');
// 本地储存文件配置
define('UPLOAD_FILE_ROOT_DIR', JJSAN_DIR_PATH. 'public/upload/');
// 本地存储文件的相对位置(含域名的)
define('UPLOAD_FILE_RELATIVE_DIR_CONTAIN_DOMAIN', ROOT . 'upload');

define("UMBRELLA_SYNC_TIME", 30*60); //机器同步雨伞默认时间

define("UMBRELLA_SLOT_INTERVAL", 3); //机器逻辑槽位与物理槽位的差值3, 物理1号槽位, 逻辑4号槽位


define("TEMPLATE_TYPE_BORROW_UMBRELLA", 1);
define("TEMPLATE_TYPE_RETURN_UMBRELLA", 2);
define("TEMPLATE_TYPE_WITHDRAW_APPLY", 3);
define("TEMPLATE_TYPE_RETURN_REMIND", 4);
define("TEMPLATE_TYPE_BROKEN_REMIND", 5);
define("TEMPLATE_TYPE_BORROW_FAIL", 6);
define("TEMPLATE_TYPE_REFUND_FEE", 7);
define("TEMPLATE_TYPE_LOSE_UMBRELLA", 8);

define("WALLET_TYPE_PREPAID", 1);           //钱包明细，充值
define("WALLET_TYPE_PAID", 2);              //钱包明细，支付
define("WALLET_TYPE_REQUEST", 3);           //钱包明细，提现申请
define("WALLET_TYPE_WITHDRAW", 4);          //钱包明细，提现到账
define("WALLET_TYPE_REFUND", 5);            //钱包明细，退款
define("WALLET_TYPE_ZHIMA_PAID", 6);        //钱包明细，芝麻支付
define("WALLET_TYPE_ZHIMA_PAID_UNCONFIRMED", 7);    //钱包明细，芝麻支付待确认

// 模板字体颜色
define("TEMPLATE_REMARK_FONT_COLOR", "#FFA405");
