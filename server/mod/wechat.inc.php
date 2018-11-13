<?php
// 若不是手机模式则需强制成手机模式, 不然会找不到模板
define('IN_MOBILE', 2);
if(IN_MOBILE == 1) {
    dheader("location: " . "{$_SERVER['PHP_SELF']}?{$_SERVER['QUERY_STRING']}&mobile=2");
    exit;
}

$act = $act ? : 'user';

// 加载控制器
@include_once "act/wechat/$act.php";

// 约定 : 一个控制器　对应　其默认视图
$view = "jjsan:wechat/$act/$opt"; // 当前所在视图

// 视图不存在
if (!file_exists("../template/touch/wechat/$act/$opt.htm")) {
    $view = "jjsan:touch/404"; // 访问的页面不存在
}

// 加载视图文件
include template($view);

