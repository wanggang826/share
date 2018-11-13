<?php
use model\Station;
use model\Api;

$opt = $opt ? : 'list';

$url = "index.php?mod=$mod&act=$act&opt=$opt";

$station        = new Station();	// 设备模型
$shopStation    = ct('shop_station');

$shop_station_id = $_GET['shop_station_id'];
switch ( $opt ) {

	case 'list':
	    $accessShops = null;
	    $accessCities = null;

	    // 非全局搜索
	    if (!$auth->globalSearch) {
            $accessCities = $auth->getAccessCities();
            $accessShops = $auth->getAccessShops();
        }

		$stations = $shopStation->searchShopStation($_GET, $page, $pageSize, $accessCities, $accessShops);

	    // 显示商铺名称
        $shopIds = array_map(function($a){
            return $a['shopid'];
        }, $stations['data']);
        $shopInfos = ct('shop')->where(['id' => $shopIds])->get();  //不要用fetch, 因为shopIds是数组
        foreach ($shopInfos as $v) {
            $newShopInfos[$v['id']] = $v;
        }

        // @todo optimize sql
        $stations['data'] = array_map(function($a) use ($newShopInfos) {
            $a['shopname'] = $newShopInfos[$a['shopid']]['name'];
            $a['desc'] = $a['desc'] ?  : '未设置';
            $a['fee_setting_name'] = $a['fee_settings'] ? ct('fee_strategy')->fetch($a['fee_settings'])['name'] : '全局配置';
            $admin = ct('admin')->fetch($a['seller_id']);
            $adminRole = ct('admin_role')->fetch($admin['role_id']);
            $a['seller_name'] = $a['seller_id'] ? $admin['name'] : '无';
            $a['seller_role_name'] = $adminRole ? $adminRole['role'] : '无';
            return $a;
        }, $stations['data']);

        unset($_GET['page']);
        $pagehtm = getPages($stations['count'], $page - 1, $pageSize, '/index.php?'.http_build_query($_GET));
		break;

    case 'setting':
        if (isset($do)) {
            switch ($do) {
                // 绑定商铺
                case 'bind':
                    $shops = ct('shop')->limit(($page - 1) * $pageSize, $pageSize)->get();
                    $count = ct('shop')->count();
                    unset($_GET['page']);
                    $pagehtm = getPages($count, $page - 1, $pageSize, '/index.php?'.http_build_query($_GET));
                    include template('jjsan:cp/shop_station/shop-set');
                    break;

                //　站点解绑商铺
                case 'unbind':
                    if (!$auth->globalSearch && !$auth->checkShopStationIdIsAuthorized($shop_station_id)) {
                        echo 'unauthorized station';
                        exit;
                    }
                    $admin->cp_shop_unbind($shopStation, $shop_station_id);
                    header("location:{$_SERVER['HTTP_REFERER']}");
                    exit;

                // 设置策略
                case 'setting_strategy':
                    if (!$auth->globalSearch && !$auth->checkShopStationIdIsAuthorized($shop_station_id)) {
                        echo 'unauthorized station';
                        exit;
                    }

                    if($_POST) {
                        if($shopStation->getField($shop_station_id, 'station_id') == 0 && $status == 1){
                            Api::output([], 1, '未绑定站点，无法启用');
                            exit;
                        }
                        if ($seller_id) {
                            $sellerInfo = ct('admin')->fetch($seller_id);
                            if (!$sellerInfo || $sellerInfo['status'] != ADMIN_USER_STATUS_NORMAL) {
                                Api::output([], 1, '归属负责人不存在或者非正常状态');
                                exit;
                            }
                        }
                        $update_shop_station_fields = [
                            'fee_settings'     => $fee_id,
                            'pictext_settings' => $pic_id,
                            'title'            => $title,
                            'address'          => $address,
                            'desc'             => $desc,
                            'seller_id'        => $seller_id,
                            'status'           => $status,
                        ];
                        $new_lbs_data = [
                            'title'   => $title,
                            'address' => $address,
                            'desc'    => $desc,
                            'enable'  => $status,
                        ];
                        $origin_lbs_data = [
                            'title'   => $shopStation->getField($shop_station_id, 'title'),
                            'address' => $shopStation->getField($shop_station_id, 'address'),
                            'desc'    => $shopStation->getField($shop_station_id, 'desc'),
                            'enable'  => $shopStation->getField($shop_station_id, 'status'),
                        ];
                        if ($origin_lbs_data != $new_lbs_data) {
                            $ret = updateStationToLBS($shopStation->getField($shop_station_id, 'lbsid'), $new_lbs_data);
                            if ($ret['errcode'] == 0) {
                                $update_shop_station_fields['status'] = $status;
                            }
                        }
                        $res = $admin->shop_station_settings($shopStation, $shop_station_id, $update_shop_station_fields);
                        if ($sid = $shopStation->getField($shop_station_id, 'station_id') && $res) {
                            ct('station')->update($sid, ['title' => $title, 'address' => $address]);
                        }
                        if ($res) {
                            Api::output([], 0, '商铺站点设置更新成功');
                        } else {
                            Api::output([], 1, '商铺站点设置更新失败');
                        }
                        exit;
                    }

                    $shop_station = $shopStation->fetch($shop_station_id);
                    $fees = ct('fee_strategy') -> fetch_all();
                    $pictexts = ct('pictext_settings') -> fetch_all();
                    $feeSetting = $shop_station['fee_settings'];
                    $picSetting = $shop_station['pictext_settings'];
                    $seller_id = $shop_station['seller_id'];
                    $all_sellers = ct('admin')->where(['status' => ADMIN_USER_STATUS_NORMAL])->get();
                    include template('jjsan:cp/shop_station/set_settings');
                    break;

                default:
            }
            exit;
        }
        break;

    // CURD 操作
    case 'show_shop_station_replace':
        $shop_station = ct('shop_station')->fetch($shop_station_id);
        include template("jjsan:cp/shop_station/shop_station_replace");
        exit;
        break;


    case 'shop_station_replace':
        // 换机操作：针对已有绑定机器的商铺站点进行更换机器的操作
        $new_sid = $_GET['sid'];
        $origin_sid = ct('shop_station')->getField($shop_station_id, 'station_id');
        $this_sid_existed = ct('station')->count_by_field('id', $new_sid);
        if (!$this_sid_existed) {
            echo json_encode(['errcode'=> 1, 'errmsg'=>'不存在该机器']);
            exit;
        }
        $this_sid_binded = ct('shop_station')->count_by_field('station_id', $new_sid);
        if ($this_sid_binded) {
            echo json_encode(['errcode'=> 2, 'errmsg'=>'该机器已绑定其他商铺站点']);
            exit;
        }
        // 1.把这个商铺站点绑定到新的机器上
        // 2.把新机器的信息（title,address）与这个商铺站点同步
        // 3.把原来的机器信息（title,address）置空
        $res = $admin->cp_replace($shop_station_id, $new_sid, $origin_sid);
        echo $res ? json_encode(['errcode'=> 0, 'errmsg'=>'换机成功']) : json_encode(['errcode'=> 1, 'errmsg'=>'换机失败']);
        exit;

    case 'shop_station_remove':
        $res = $admin->cp_remove();
        echo $res ? json_encode(['errcode'=>0, 'errmsg'=>'撤机成功']) : json_encode(['errcode'=>1, 'errmsg'=>'撤机失败']);
        exit;

    case "ajax-shop-set":
        if (!$auth->globalSearch && !$auth->checkShopStationIdIsAuthorized($shop_station_id)) {
            echo 'unauthorized station';
            exit;
        }
        $admin->cp_shop_bind($shopStation, $shop_station_id, $shopid);
        header("location:index.php?mod=cp&act=$act&opt=setting&page=$page");
        exit;

    case 'show_shop_station_go_up':
        include template("jjsan:cp/shop_station/shop_station_go_up");
        exit;
        break;

    case 'shop_station_go_up':
        // 上机操作：在没有绑定机器的时候绑定一台新的机器
        $new_sid = $_GET['sid'];
        $this_sid_existed = ct('station')->count_by_field('id', $new_sid);
        if (!$this_sid_existed) {
            echo json_encode(['errcode'=> 1, 'errmsg'=>'不存在该机器']);
            exit;
        }
        $this_sid_binded = $shopStation->count_by_field('station_id', $new_sid);
        if ($this_sid_binded) {
            echo json_encode(['errcode'=> 2, 'errmsg'=>'该机器已绑定其他商铺站点']);
            exit;
        }
        $res = $admin->cp_go_up($shopStation, $shop_station_id, $new_sid);
        echo $res ? json_encode(['errcode'=>0, 'errmsg'=>'上机成功']) : json_encode(['errcode'=>1, 'errmsg'=>'上机失败']);
        exit;

    case 'get_shop_station_list':
        $accessShops = null;
        $accessCities = null;
        // 非全局搜索
        if (!$auth->globalSearch) {
            $accessCities = $auth->getAccessCities();
            $accessShops = $auth->getAccessShops();
        }
        $shop_stations = $shopStation->searchShopStation($_GET, 0, 0, $accessCities, $accessShops);
        foreach ($shop_stations['data'] as $key => $value) {
            $shop_station_data[$key]['value'] = $value['title'];
            $shop_station_data[$key]['data'] = $value['id'];
        }
        unset($shop_stations);

        echo $shop_station_data ? json_encode(['suggestions'=>$shop_station_data]) :json_encode(['suggestions'=>[]]);
        exit;

	default:
		redirect("您找的页面不存在");
}