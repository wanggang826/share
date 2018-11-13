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
$LOG_FILENAME = '_wxpaynotify';
// 加载配置
require_once JJSAN_DIR_PATH . '/cfg.inc.php';

// 加载类库
require_once JJSAN_DIR_PATH . '/lib/scurl.class.php';
require_once JJSAN_DIR_PATH . '/lib/wxapi.class.php';
require_once JJSAN_DIR_PATH . '/lib/wxpay.class.php';
require_once JJSAN_DIR_PATH . '/lib/swapi.class.php';

// 加载业务函数
require_once JJSAN_DIR_PATH . '/func.inc.php';

$postStr = $GLOBALS["HTTP_RAW_POST_DATA"] ? : file_get_contents("php://input");

LOG::DEBUG("wxpay notify");
LOG::DEBUG($postStr);

if (!empty($postStr)){
	$payResult = new WxPayMicroPay();
	$payResult->FromXml($postStr);
	//有签名且签名正确
	if(!$payResult->IsSignSet() || $payResult->makeSign() != $payResult->getSign()){
		LOG::ERROR("签名错误！");
		exit('sign error');
	}
	libxml_disable_entity_loader(true);
	$postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
	$paynotify = array(
		'appid' => (string) $postObj->appid,
		'attach' => (string) $postObj->attach,
		'bank_type' => (string) $postObj->bank_type,
		'cash_fee' => (string) $postObj->cash_fee,
		'is_subscribe' => (string) $postObj->is_subscribe,
		'mch_id' => (string) $postObj->mch_id,
		'nonce_str' => (string) $postObj->nonce_str,
		'openid' => (string) $postObj->openid,
		'out_trade_no' => (string) $postObj->out_trade_no,
		'result_code' => (string) $postObj->result_code,
		'return_code' => (string) $postObj->return_code,
		'return_msg' => (string) $postObj->return_msg,
		'sign' => (string) $postObj->sign,
		'time_end' => (string) $postObj->time_end,
		'total_fee' => (string) $postObj->total_fee,
		'trade_type' => (string) $postObj->trade_type,
		'transaction_id' => (string) $postObj->transaction_id,
	);


	if ( $paynotify['return_code'] == 'SUCCESS' && $paynotify['result_code'] == 'SUCCESS' ) {
		$orderid = $paynotify['out_trade_no'];
		$paid  = round($paynotify['total_fee']/100, 2);
        // 支付成功
        notifyOrderPaid($orderid , $paid);
		echo "<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[$orderid]]></return_msg></xml>";
		LOG::DEBUG("reply success to weixin server");
		exit;
	}
}
