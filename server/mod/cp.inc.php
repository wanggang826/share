<?php
use model\Admin;
use model\Auth;

// 默认模块
$act = $act ?: 'admin';
$opt = $opt ?: 'index';
$page = isset($page) ? $page + 0 : 1;
$pageSize = RECORD_LIMIT_PER_PAGE;

// 权限控制 导航
include_once 'jjsan_nav_tree.inc.php';

// 管理员区域　导航
include_once 'area_nav_tree.inc.php';

// 管理员登录
$admin = new Admin();
if(!in_array($opt, ['login', 'register']) && !$admin->isLogin()) {
    header('location:index.php?mod=cp&act=admin&&opt=login');
    exit;
}

if (!in_array($opt, ['login', 'register', 'pwd'])) {
    LOG::DEBUG("admin entry admin id: " . $admin->adminInfo['id']);
    LOG::DEBUG("admin entry params: " . json_encode($_GET));
}

// 加载管理员对应权限
$auth = new Auth($admin->adminInfo['id']);
$auth_access = $auth->getAccess();

// 左边栏树
$nav_access_tree = $auth->getNavAccessTree($jjsan_nav_tree);

// 按钮检查用途
$cdo = $nav_access_tree[$act]['sub_nav'][$opt]['do'];

// 权限检查 & 加载控制器和视图
// 确认权限
// 是: 加载控制器和视图,不存在的页面显示页面不存在
// 否: 不加载控制器,直接显示未授权页面
$is_access_action = $auth->isAuthorizedUrl($act, $opt, $do, $jjsan_nav_tree);
if($is_access_action) {

    // 加载控制器
    @include_once "act/cp/$act.php";

    // 约定 : 一个控制器　对应　其默认视图
    $view = "jjsan:cp/$act/$opt"; // 当前所在视图

    // 视图不存在
    if(!file_exists("../template/cp/$act/$opt.htm")) {
        $view = "jjsan:cp/error/404"; // 访问的页面不存在
    }

    // url仅为域名的情况下
    if ($_SERVER['REQUEST_URI'] == '/' || $_SERVER['REQUEST_URI'] == '/index.php') {
        $view = "jjsan:cp/admin/help";
    }
} else {
    $view = "jjsan:cp/error/no-access"; // 未授权访问页面
}

// 加载视图文件
include template('jjsan:cp/index');