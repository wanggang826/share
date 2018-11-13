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
$LOG_FILENAME = '_alipaynotify';
// 加载配置
require_once JJSAN_DIR_PATH . '/cfg.inc.php';

// 加载类库
require_once JJSAN_DIR_PATH . '/lib/scurl.class.php';
require_once JJSAN_DIR_PATH . '/lib/swapi.class.php';
require_once JJSAN_DIR_PATH . '/lib/alipay/AlipayAPI.php';

// 加载业务函数
require_once JJSAN_DIR_PATH . '/func.inc.php';

$postStr = $GLOBALS["HTTP_RAW_POST_DATA"] ? : file_get_contents("php://input");
LOG::DEBUG("alipay notify");
LOG::DEBUG(var_export($_POST, true));

if(! AlipayAPI::verifyPayNotifyV2()) {
  echo 'fail';
  exit;
}

//文档 https://doc.open.alipay.com/docs/doc.htm?spm=a219a.7629140.0.0.Zs8eVA&treeId=60&articleId=104790&docType=1
if ($_POST['trade_status'] == 'TRADE_FINISHED' || $_POST['trade_status'] == 'TRADE_SUCCESS') {
    $orderid = $_POST['out_trade_no'];
    $paid  = round($_POST['total_amount'], 2) ;
    // 记录用户充值事件 @todo 需要重新添加
    notifyOrderPaid($orderid , $paid);
}
echo "success";
LOG::DEBUG("reply success to alipay server");

exit;
