<?php
define('DISABLEXSSCHECK', true);
define('PLUGIN_NAME', 'jjsan');
define('JJSAN_DIR_PATH', __DIR__ . '/../');
define('DZ_ROOT', JJSAN_DIR_PATH . '/../../');

// $LOG_FILENAME配置log文件名称
$LOG_FILENAME = '_rent';
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

// 加载业务函数
require_once JJSAN_DIR_PATH . '/func.inc.php';

// $_GET数据过滤
$_GET = array_map(function($v){
    return is_array($v) ? $v : trim($v);
}, $_GET);
extract($_GET);

// 检查消息是否过期
if($_GET['t'] && ($_GET['t'] + 5*60) < time()) {
    header("location:index.php?mod=wechat&act=user&opt=pay#/oneKeyUse");
} else {
    $platform = getPlatform() ? 'alipay' : 'wx' ;
    $qrcode = ct('qrcode')->fetch($stationid)[$platform];
    header("location:index.php?mod=wechat&act=user&opt=pay&flag=1&qrcode=$qrcode#/oneKeyUse");
}
exit;