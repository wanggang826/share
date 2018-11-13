<?php
use model\Api;

$shop         = ct('shop');
$shopType     = ct('shop_type');
$shopStation  = ct('shop_station');

switch($opt){

    // 获取所有商铺的id和地址
    case 'get_all_shop_locate':
        $shops = $shop->select('id as data, name as value')->get();
        Api::output($shops, Api::SUCCESS, Api::$msg[SUCCESS]);
        break;

    case 'get_shop_info':
        $shop_id = $_GET['shop_id'];
        $shop_info = $shop->select('id, name, locate')->where(['id' => $shop_id])->get();
        $shopStations_info = $shopStation->where(['shopid' => $shop_id, 'status' => 0])->get();
        $result = ['shop' => $shop_info, 'shop_station' => $shopStations_info];
        Api::output($result, Api::SUCCESS, Api::$msg[SUCCESS]);
        break;

    case 'get_default_shop_info':
        $shop_info = $shop->select('id, name, locate')->where(['id' => 553])->get();
        $shop_info_2 = $shop->select('id, name, locate')->where(['id' => 24])->get();
        $shopStations_info = $shopStation->where(['shopid' => 553, 'status' => 0])->get();
        $shopStations_info_2 = $shopStation->where(['shopid' => 24, 'status' => 0])->get();
        $result = [
                    ['shop' => $shop_info, 'shop_station' => $shopStations_info],
                    ['shop' => $shop_info_2, 'shop_station' => $shopStations_info_2]
                ];
        Api::output($result);
        break;


    case 'get_all_shop_type':
        $result = $shopType->select('id, type')->get();
        Api::output($result);
        break;

	case 'get_title_list':
		$admin_id = $_GET['admin_id'];
		$city = $_GET['city'];
		// $shop_list = $city ? C::t('#jjsan#jjsan_shop')->searchTitleByCity($city) : C::t('#jjsan#jjsan_shop')->getBriefAll($admin_id);
		$authcity = ct('admin_city')->select('city')->where(['admin_id'=>$admin_id, 'status'=>1])->first();
		$authcity = json_decode($authcity['city']);
        foreach($authcity as $v) {
            foreach($v as $vv) {
                $ac[] = $vv;
            }
        }
		$authshop = C::t('#jjsan#jjsan_admin_shop')->select('shop_id')->where(['admin_id'=>$admin_id, 'status'=>1])->get();
		$as = array_map(function($v){
            return $v['shop_id'];
        }, $authshop);

		$cities['city'] = $ac;
		$shops['id'] = $as;

		$auth[] = $shops;
		array_push($auth, $cities);
		$shop_list = ct("shop")->anyWhere($auth)->get();
		foreach ($shop_list as &$value) {
			$value['value'] = $value['name'];
			$value['data'] = $value['id'];
			unset($value['name']);
			unset($value['id']);
	    }
	    $shop_data = array('suggestions'=>$shop_list);
	    echo json_encode($shop_data);
	    break;

    default:
        Api::output([], 1, 'unknown request');

}
