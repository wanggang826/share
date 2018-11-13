<?php
define('PLUGIN_NAME', 'jjsan');
define('IN_API', true);
define('CURSCRIPT', 'api');
define('DISABLEXSSCHECK', true);
define('JJSAN_DIR_PATH', __DIR__ . '/../');
define('DZ_ROOT', JJSAN_DIR_PATH . '/../../');

require DZ_ROOT . 'class/class_core.php';
$discuz = C::app();
$discuz->init();

// 加载基于PSR0/4规范的类
require_once JJSAN_DIR_PATH . 'vendor/autoload.php';

// $LOG_FILENAME配置log文件名称
$LOG_FILENAME = '_zhimanotify';
// 加载配置
require_once JJSAN_DIR_PATH . 'cfg.inc.php';

// 加载类库
require_once JJSAN_DIR_PATH . 'lib/scurl.class.php';
require_once JJSAN_DIR_PATH . 'lib/wxapi.class.php';
//require_once 'lib/aes.php';

// 加载业务函数
require_once JJSAN_DIR_PATH . 'func.inc.php';

LOG::DEBUG ("GET: " . var_export ($_GET, true ));
$result = json_decode($_GET['biz_content'], true);
LOG::DEBUG('biz content:' . var_export($result, true));

if(!$result['success']) {
    LOG::ERROR('zhima notify error');
    exit;
} else {
    header('location: /index.php?mod=wechat&act=user&opt=pay&orderid='. $result['out_order_no'] . '#/afterPay');
    exit;
}

