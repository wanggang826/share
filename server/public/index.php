<?php
define('DISABLEXSSCHECK', true);
define('PLUGIN_NAME', 'jjsan');
define('JJSAN_DIR_PATH', __DIR__ . '/../');
define('DZ_ROOT', JJSAN_DIR_PATH . '/../../');

// $LOG_FILENAME配置log文件名称
// 加载配置
// 微信支付宝api和小程序weapp区分开
if ($_GET['mod'] == 'api' && $_GET['act'] == 'platform') {
    $LOG_FILENAME = '_' . 'api';
}elseif ($_GET['mod'] == 'api' && $_GET['act'] == 'weapp') {
    $LOG_FILENAME = '_' . 'weapp';
}
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

// 加载业务函数
require_once JJSAN_DIR_PATH . '/func.inc.php';


// $_GET数据过滤
$_GET = array_map(function($v){
    return is_array($v) ? $v : trim($v);
}, $_GET);
extract($_GET);

if(defined('IN_MOBILE')){
    $mod = $mod ? : 'wechat'; // 手机端　默认站点微信商城
}else{
    $mod = $mod ? : 'cp'; // PC端　站点管理后台
}

switch ( $mod ) {

	// 后台管理系统
	case 'cp':
		require_once JJSAN_DIR_PATH . "/mod/cp.inc.php";
		break;

	// 微信端
	case "wechat":
		require_once JJSAN_DIR_PATH . "/mod/wechat.inc.php";
		break;

	// api 接口
	case "api":
		require_once JJSAN_DIR_PATH . "/mod/api.inc.php";
		break;

    // 测试模块 所有测试用的功能都移到此处
    case 'test':
        !ENV_DEV && exit('need test environment');
        require_once JJSAN_DIR_PATH . "/mod/test.inc.php";
        break;

    // 支付宝8.6活动模块，活动完成后删除
    case 'activity':
        if (time() > strtotime('2017-08-14 00:00:00')) {
            echo '活动结束了';
            exit;
        }
        require_once JJSAN_DIR_PATH . '/mod/activity.inc.php';
        break;

    // 小程序入口
    case 'weapp':
        require_once JJSAN_DIR_PATH . '/mod/weapp.inc.php';
        break;

    default:


}
