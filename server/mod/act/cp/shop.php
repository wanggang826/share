<?php
use model\Api;
use model\FileUpload;
use model\Shop;

$opt = $opt ?:"list";

switch($opt){

    // 区域下的商铺 和 绑定的商铺 分开显示
    case 'list':
        $order = 'id desc';
        $adminid = $admin->adminInfo['id'];
        if (!$auth->globalSearch) {
            // 授权的商铺id
            $anyWhere[] = ['id' => $auth->getAccessShops()];
            // 授权的区域下的所有商铺
            $anyWhere[] = ['city' =>$auth->getAccessCities()];
        }
        if (isset($province) && !empty($province)) {
            $where['province'] = $province;
        }
        if (isset($city) && !empty($city)) {
            $where['city'] = $city;
        }
        if (isset($area) && !empty($area)) {
            $where['area'] = $area;
        }
        if (isset($keyword) && !empty($keyword)) {
            $where['name'] = ['value' => '%' . $keyword . '%', 'glue' => 'like'];
        }
        $shops = ct('shop')->where($where)->anyWhere($anyWhere)->limit(($page - 1) * $pageSize, $pageSize)->order($order)->get();
        $count = ct('shop')->where($where)->anyWhere($anyWhere)->count();
        // 获取商铺类型
        $shop_types = ct('shop_type')->get();
        foreach ($shop_types as $type) {
            $shopTypes[$type['id']] = $type['type'];
        }

        $shops = array_map(function($a) use ($shopTypes) {
            $a['logo'] = json_decode($a['logo']);
            if(!$a['logo']){
                //$type = $a['type'];
                //$shop_type = ct('shop_type')->fetch($type);
                //$logo = json_decode($shop_type['logo']);
                //$a['logo'] = $logo;
                $a['default'] = true;
            }
            $a['carousel'] = json_decode($a['carousel']);
            $a['shoptype'] = $shopTypes[$a['type']] ? : '无';
            return $a;
        }, $shops);
        unset($_GET['page']);
        $pagehtm = getPages($count, $page - 1, $pageSize, '/index.php?'.http_build_query($_GET));
        break;

    case 'shop_type_list':
        if (isset($keyword) && !empty($keyword)) {
            $where['type'] = ['value' => '%' . $keyword . '%', 'glue' => 'like'];
        }
        $shop_types = ct('shop_type')->where($where)->limit(($page - 1) * $pageSize, $pageSize)->order($order)->get();
        $count = ct('shop_type')->count();
        $shop_types = array_map(function($a){
            $a['logo'] = json_decode($a['logo']);
            return $a;
        },$shop_types);
        $pagehtm = getPages($count, $page - 1, $pageSize, '/index.php?'.http_build_query($_GET));
        break;

    case 'add':
        if(isset($submit)){
            $res = $admin->shop_add();
            if($res){
                $url = "/index.php?mod=$mod&act=$act&opt=list&rst=success";
                header("location:$url");
                exit;
            }
        }
        // 获取商铺类型
        $shop_types = ct('shop_type')->get();
        $url = "/index.php?mod=$mod&act=$act";

        // 所有省份
        $provinces = array_map(function($v){
            return $v['province'];
        }, $area_nav_tree);
        $action = "index.php?mod=$mod&act=$act&opt=$opt&submit=1";
        break;

    case 'add_shop_type':
        if ($_POST) {
            if($type){
                $res = $admin->shop_type_add($type);
                if ($res) {
                    Api::output([], 0, '添加成功');
                } else {
                    Api::output([], 1, '添加失败');
                }
            }
            exit;
        }
        break;

    case 'update':
        if (!$auth->globalSearch && !$auth->checkShopIdIsAuthorized($shopid)) {
            echo 'unauthorized shop';
            exit;
        }
        $shop = new Shop();
        // 更新操作
        if(isset($submit)){
            $res = $admin->shop_edit($shopid, $shop);
            if ($res) {
                Api::output([], 0, '更新成功');
            } else {
                Api::output([], 1, '更新失败');
            }
            exit;
        }
        // 显示编辑界面
        $shop = ct('shop')->fetch($shopid);
        // 获取商铺类型
        $shop_types = ct('shop_type')->get();
        $url = "/index.php?mod=$mod&act=$act";
        $province = $shop['province'];
        $city = $shop['city'];
        $area = $shop['area'];
        include template('jjsan:cp/shop/update');
        exit;
        break;

    // 更新logo
    case 'update_logo':
        if (!$auth->globalSearch && !$auth->checkShopIdIsAuthorized($shopid)) {
            echo 'unauthorized shop';
            exit;
        }

        //  更新logo
        if(isset($submit) && $type=='shop'){
            $res = $admin->logo_update($id);
            if ($res) {
                Api::output([], 0, 'logo更新成功');
            } else {
                Api::output([], 1, 'logo更新失败');
            }
            exit;
        } elseif (isset($submit) && $type=='shop_type'){
            $files = FileUpload::img(UPLOAD_FILE_ROOT_DIR.'logo', UPLOAD_FILE_RELATIVE_DIR_CONTAIN_DOMAIN.'/logo');
            $res = ct('shop_type')->update($id, ['logo' => json_encode($files)]);
            if ($res) {
                Api::output([], 0, 'logo更新成功');
            } else {
                Api::output([], 1, 'logo更新失败');
            }
            exit;
        }

        // 显示更新文件页面
        $action = "index.php?mod=$mod&act=$act&opt=$opt&type=$type&page=$page&id=$id&submit=1";
        include template('jjsan:cp/shop/update_logo');
        exit;
        break;

    // 更新轮播图
    case 'update_carousel':
        if (!$auth->globalSearch && !$auth->checkShopIdIsAuthorized($shopid)) {
            echo 'unauthorized shop';
            exit;
        }
        $shop = new Shop();
        // 更新操作
        if($submit){
            $res = $admin->carousel_update($shopid, $shop, $mats);
            if ($res) {
                Api::output([], 0, '轮播图更新成功');
            } else {
                Api::output([], 1, '轮播图更新失败');
            }
            exit;
        }

        $shops = $shop -> get($shopid);
        if($shops){
            $shop = $shops[$shopid];
        }
        // 轮播图
        $imgs = json_decode($shop['carousel']);
        // 显示更新文件页面
        $mats = scandir(JJSAN_DIR_PATH . 'public/upload/carousel');
        $action = "index.php?mod=cp&act=shop&opt=$opt&submit=1&page=$page&shopid=$shopid";
        include template('jjsan:cp/shop/update_carousel');
        exit;
        break;

    case "get_area_info":
        if($ajax == 1) {
            if($province) {
                if($city) {
                    Api::output(getAreasByCity($province, $city, $area_nav_tree));
                } else {
                    Api::output(getCitiesByProvince($province, $area_nav_tree));
                }
            }
        }
        exit;
        break;

    default:
        header("location:index.php?mod=$mod&act=$act&opt=list");
        exit;
}
