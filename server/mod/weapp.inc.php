<?php
use model\Weapp;
$weapp = new Weapp();
if(!defined('IN_DISCUZ')) {
	exit('Access Denied');
}

// require_once __DIR__ . '/user_check.inc.php';

$data = $GLOBALS["HTTP_RAW_POST_DATA"] ? : file_get_contents("php://input");

LOG::DEBUG("enter weapp and get data:" . $data);

if ( $_GET['echostr'] ) {
	Weapp::valid(true);
}else {
	echo "exit";
	exit;
}
