<?php
use model\Api;
use model\Shop;
use model\User;

require_once JJSAN_DIR_PATH . 'lib/swapi.class.php';
require_once JJSAN_DIR_PATH . 'lib/lbsapi.class.php';
require_once JJSAN_DIR_PATH . 'mod/user_check.inc.php';

$opt = $opt ?:"list";

// 检查消息是否过期
if(isset($t) && ($t + 5*60) < time()) {
    include template('jjsan:shop_expire');
    exit;
}

// 实例化用户模型
$user = new User();

if ($platform == PLATFORM_WX) {
    // 调用扫一扫接口用
    $jt = getJsApiTicketValue();
    if(!$jt) {
        redirect('need js api');
        exit;
    }
}
$signPackage = wxAPI::GetSignPackage($jt);


switch($opt){
    case 'newMap':
        break;
    # 附近网点
	case "list":
		$title = "附近网点";
        // 商铺列表
        if(isset($ajax)){
            $shop_stations = ct('shop_station')->getAllInfo($shop_station_ids, true);
            $shop_stations = array_map(function($a){
                if(!$a['shoplogo']){
                    $type = $a['type'];
                    $type_info = ct('shop_type')->fetch($type);
                    $a['shoplogo'] = json_decode($type_info['logo']);
                }
                return $a;
            },$shop_stations);
            echo json_encode($shop_stations,true);
            exit;
		}
		break;

	case 'map2':
       $title = "附近网点2";
        // 商铺列表
       if(isset($ajax)){
            echo json_encode(ct('shop_station')->getAllInfo($shop_station_ids, true),true);
            exit;
        }
       break;

    # 商铺列表搜索
    case "filter":
        $shop_stations = ct('shop_station')->where(['status' => 1])->get();
        $shop_station_ids = array_column($shop_stations,'id');
        $shops = ct('shop_station')->getAllInfo($shop_station_ids, true, true);
        foreach ($shops as $k => $shop) {
            if(strpos($shop['title'].$shop['address'], $key_str) === false){
                unset($shops[$k]);
            } else {
                if(!$shop['shoplogo']){
                    $type = $shop['type'];
                    $type_info = ct('shop_type')->fetch($type);
                    $shop['shoplogo'] = json_decode($type_info['logo']);
                }
            }
        }
        $sid = [];
        foreach ($shop_stations as $k => $v){
            foreach ($shops as $kk => $shop){
                if(!in_array($v['shopid'], $sid) && $v['shopid'] == $shop['shopid']){
                    $shops[$kk]['lng'] = $v['longitude'];
                    $shops[$kk]['lat'] = $v['latitude'];
                    $sid[] = $v['shopid'];
                }
            }
        }
//        $shops = array_chunk($shops, 5)[$page];
        echo json_encode($shops);
        exit;

    # 附近网点(地图)
    case "map":
        $title = "附近网点";
        // 商铺列表
        if(isset($ajax)){
            echo json_encode(ct('shop_station')->getAllInfo($shop_station_ids, true),true);
            exit;
        }
        break;

    # 网点详情
	case "detail":
		// 查寻下这个站点的商铺下的所有站点
		$shop = new Shop();
		$shop_station_table = ct('shop_station');
		$shopid = $shop_station_table->fetch($shop_station_id)['shopid'];
		//　该商铺下的所有站点
		if($shopid){
			$shop_stations = $shop -> shop_stations($shopid, 0, 0, 1);
			$shop_station_ids = array_column($shop_stations, 'id');
		}else{
			$shop_station_ids[] = $shop_station_id;
		}
		$shopInfo = ct('shop')->fetch($shopid);
		if($shopInfo['province'] && ($shopInfo['province'] == $shopInfo['city'])) {
            $shopInfo['address'] = $shopInfo['province'] . $shopInfo['area'] . $shopInfo['locate'];
        } else {
            $shopInfo['address'] = $shopInfo['province'] . $shopInfo['city'] . $shopInfo['area'] . $shopInfo['locate'];
        }
		$sta = $shop_station_table -> getAllInfo($shop_station_ids);
		break;

    # map页面的网点详情
    case "map_detail":
        // 查寻下这个站点的商铺下的所有站点
        $shop = new Shop();
        $shop_station_table = ct('shop_station');
        $shopid = $shop_station_table->fetch($shop_station_id)['shopid'];
        //　该商铺下的所有站点
        if($shopid){
            $shop_stations = $shop -> shop_stations($shopid, 0, 0, 1);
            $shop_station_ids = array_column($shop_stations, 'id');
        }else{
            $shop_station_ids[] = $shop_station_id;
        }
        $shopInfo = ct('shop')->fetch($shopid);
        if($shopInfo['province'] && ($shopInfo['province'] == $shopInfo['city'])) {
            $shopInfo['address'] = $shopInfo['province'] . $shopInfo['area'] . $shopInfo['locate'];
        } else {
            $shopInfo['address'] = $shopInfo['province'] . $shopInfo['city'] . $shopInfo['area'] . $shopInfo['locate'];
        }
        $sta = $shop_station_table -> getAllInfo($shop_station_ids);
        echo json_encode(['sta' => $sta, 'shopInfo' => $shopInfo, 'status' => 1]);
        exit;
        break;
    # 管理 撤机走api,所以没有页面
    case 'manage_page':
        // 判断权限
        $installs = json_decode( C::t('common_setting')->fetch('jjsan_install_man'), true );
        if(! $installs[$uid] || ! $stationid) {
            redirect('没有权限', 'index.php?mod=wechat&act=user&opt=ucenter');
        }
        // 没有绑定商铺的站点，隐藏撤机换机按钮
        if (!ct('shop_station')->where(['station_id' => $stationid, 'shopid' => ['value' => 0, 'glue' => '>']])->first()) {
            $hidden_button = true;
        }
		break;

    # 槽位操作
    case 'slot_mgr':
        // 判断权限
        $installs = json_decode( C::t('common_setting')->fetch('jjsan_install_man'), true );
        if(! $installs[$uid] || ! $stationid) {
            redirect('没有权限', 'index.php?mod=wechat&act=user&opt=ucenter');
        }
        // 打开槽位
        if(isset($do)) {
            LOG::DEBUG("station mgr station id :" . $stationid . ', openid:' . $openid . ', user id:' . $uid);
            LOG::DEBUG("manual borrow, stationid: $stationid , slot: $slot ");
            $slot = json_decode($slot);

            // 向应用端发送指令 stationid
            switch ($do) {
                case 'open':
                    foreach($slot as $value){
                        $value = (integer)$value;
                        swAPI::slotUnlock($stationid, $value);
                        $user->log_slot_lock($uid, $stationid, $value);
                        sleep(7);
                    }
                    break;

                case 'close':
                    foreach($slot as $value) {
                        $value = (integer)$value;
                        swAPI::slotLock($stationid, $value);
                        $user->log_slot_unlock($uid, $stationid, $value);
                        sleep(7);
                    }
                    break;

                case 'manual_lent':
                    foreach($slot as $value) {
                        $value = (integer)$value;
                        swAPI::manually_lend($stationid, $value);
                        $user->log_manually_lend($uid, $stationid, $value);
                        sleep(7);
                    }
                    break;

                default:
            }
            Api::output([], 0, '命令发送成功');
            exit;
        }

        $stationInfo = ct('station')->fetch($stationid);
        $slotsStatus = ct('station')->getSlotsStatus($stationid);
        $isStationOnline = swAPI::isStationOnline($stationid) ? true : false;
        break;

    # 换机
	case 'shop_station_replace':
        // 判断权限
        $installs = json_decode( C::t('common_setting')->fetch('jjsan_install_man'), true );
        if(! $installs[$uid] || ! $stationid) {
            redirect('没有权限', 'index.php?mod=wechat&act=user&opt=ucenter');
        }
        // 绑定了shopid的站点才能进行操作
        if (!ct('shop_station')->where(['station_id' => $stationid, 'shopid' => ['value' => 0, 'glue' => '>']])->first()) {
            redirect('该机器还未绑定商铺，请先绑定商铺', "index.php?mod=wechat&act=shop&opt=manage_page&stationid=$stationid");
        }
        $stationInfo = ct('station')->fetch($stationid);
        $shopStationInfo = ct('shop_station')->where(['station_id' => $stationid])->first();
        $shopInfo = ct('shop')->fetch($shopStationInfo['shopid']);
		break;

    # 初始化,即绑定商铺
    case 'init_addr':
        $shop        = ct('shop');
        $station     = ct('station');
        $shopStation = ct('shop_station');
        LOG::DEBUG("init_addr: stationid: $stationid , uid: $uid , do: $do");
        $installs = json_decode( C::t('common_setting')->fetch('jjsan_install_man'), true );
        if(!$installs[$uid] || ! $stationid) {
            LOG::DEBUG("in init_addr process , access not permission, uid: $uid , stationid: $stationid ");
            redirect('没有权限', 'index.php?mod=wechat&act=user&opt=ucenter');
        }

        // 站点已绑定商铺直接跳转管理页面
        if (!empty($stationid) && $shopStation->where(['station_id' => $stationid, 'shopid' =>['value' => 0, 'glue' => '>']])->first()) {
            header("location: index.php?mod=wechat&act=shop&opt=manage_page&stationid=$stationid");
            exit;
        }

        switch ($do) {

            # 绑定站点到解绑的商铺站点
            case 'bind':
                // 更新绑定信息
                LOG::DEBUG("stationid:$stationid, lbsid:$lbsid, shop_station_id:$shop_station_id");
                $data = ['enable' => 1];
                $res = updateStationToLBS($lbsid, $data);
                if ($res['errcode'] == 0) {
                    $shopStation->updateFields($stationid, ['station_id' => 0]);
                    $shopStation->update($shop_station_id, ['station_id'=>$stationid, 'status' => 1]);
                    $station->update($stationid, ['title' => $_GET['title'], 'address' => $_GET['address']]);
                    echo json_encode(['errcode'=>0, 'errmsg'=>'bind success']);
                    $user->log_rebind($uid, $shop_station_id, $stationid);
                } else {
                    echo json_encode(['errcode'=>$res['status'], 'errmsg'=>$res['message']]);
                }
                exit;
                break;

            # 新增商铺
            case 'add':
                LOG::DEBUG("post data: " . print_r($_GET, 1));
                // 必填参数验证
                if (!$stationName || !$stationProvince || !$stationCity || !$stationArea || !$stationStreet || !$type || !$stationDesc || !$phone || !$stime || !$etime) {
                    LOG::INFO('check empty fail');
                    echo json_encode(makeErrorData('缺少必填参数', '新增失败'));
                    exit;
                }
                // 验证shop type 是否存在
                if (!ct('shop_type')->fetch($type)) {
                    LOG::INFO('shop type check fail');
                    echo json_encode(makeErrorData('商铺类型不存在', '新增失败'));
                    exit;
                }
                // 省市区是否合法
                require_once JJSAN_DIR_PATH . 'mod/area_nav_tree.inc.php';
                if (!checkProvenceCityAreaLegal($area_nav_tree, $stationProvince, $stationCity, $stationArea)) {
                    LOG::INFO('check province city area legal fail');
                    echo json_encode(makeErrorData('省市区不存在', '新增失败'));
                    exit;
                }


                // 替换中文符号为英文符号
                $stationName = str_replace(['（', '）', '《', '》'], ['(', ')', '(', ')'], $stationName);
                $stationStreet = str_replace(['（', '）', '《', '》'], ['(', ')', '(', ')'], $stationStreet);
                $stationDesc = str_replace(['（', '）', '《', '》'], ['(', ')', '(', ')'], $stationDesc);

                $first_title = $stationName . ' A';

                $isMunicipality = false;
                if ($stationProvince && $stationProvince == $stationCity) {
                    $stationCity = ''; //去掉直辖市重复的情况
                    $isMunicipality = true;
                }

                $ret = lbsAPI::createPOI($first_title, $latitude, $longitude, $stationProvince.$stationCity.$stationArea.$stationStreet);
                LOG::DEBUG('create new poi: ' . print_r($ret, true));
                if ($ret['status'] != 0) {
                    echo json_encode(makeErrorData('POI创建失败', '新增失败'));
                    exit;
                }
                $lbsid = $ret['id'];
                $shop_id = $shop->insert([
                    'name'     => $stationName,
                    'province' => $stationProvince,
                    'city'     => $isMunicipality ? $stationProvince : $stationCity, // 直辖市 city使用省份
                    'area'     => $stationArea,
                    'locate'   => $stationStreet,
                    'cost'     => $cost,
                    'phone'    => $phone,
                    'stime'    => $stime,
                    'etime'    => $etime,
                    'type'     => $type,
                ], true);

                // new shop_station record
                $shopStation->updateFields($stationid, ['station_id' => 0]);
                $shop_station_id = $shopStation->insert([
                    'shopid'     => $shop_id,
                    'station_id' => $stationid,
                    'title'      => $first_title,
                    'address'    => $stationProvince . $stationCity . $stationArea . $stationStreet,
                    'desc'       => $stationDesc,
                    'longitude'  => $longitude,
                    'latitude'   => $latitude,
                    'status'     => 1,
                ], true);
                $station->update($stationid, ['title' => $first_title, 'address' => $stationProvince . $stationCity . $stationArea . $stationStreet]);
                LOG::DEBUG("new shop id:$shop_id, new shop station id:$shop_station_id");
                $user->log_add_shop($uid, $shop_station_id, $shop_id, $stationid);
                $res = bindMapPoint($lbsid, $shop_station_id, 1);
                LOG::DEBUG('add new shop and shop station: ' . print_r($res, true));
                echo json_encode($res);
                exit;
                break;

            # 绑定站点到已有的商铺
            case 'bind_shop':
                // 根据shop的name，自动确定shop_staion为shop的name后面加ABCD
                // 由于商铺自身没有经纬度，所以需要以本商铺下的某一个商铺站点的经纬度为来定位传参到lbs上
                // 解除原有绑定，并把机器的title和address填上

                $shop = $shop->fetch($shop_id);

                // 因为需要用到经纬度，所以加了经纬度不为空的限制
                $shopStationInfo = $shopStation
                    ->select('latitude, longitude, seller_id')
                    ->where(['shopid' => $shop_id, 'latitude' => ['value' => 0, 'glue' => '>'], 'longitude' => ['value' => 0, 'glue' => '>']])
                    ->first();
                $latitude = $shopStationInfo['latitude'];
                $longitude = $shopStationInfo['longitude'];
                $seller_id = $shopStationInfo['seller_id'];

                $shopStation->updateFields($stationid, ['station_id' => 0]);
                $new_title = $shopStation->getNewTitleOfShopStation($shop_id);

                // 过滤直辖市中重复的城市名称
                if ($shop['province'] == $shop['city']) {
                    $shop['province'] = '';
                }
                $ret = lbsAPI::createPOI($new_title, $latitude, $longitude, $shop['province'].$shop['city'].$shop['area'].$shop['locate']);
                LOG::DEBUG('create new poi: ' . print_r($ret, true));
                if ($ret['status'] != 0) {
                    echo json_encode($ret);
                    exit;
                }
                $lbsid = $ret['id'];
                $shop_station_id = $shopStation->insert([
                    'shopid'     => $shop_id,
                    'station_id' => $stationid,
                    'lbsid'      => $lbsid,
                    'title'      => $new_title,
                    'address'    => $shop['province'] . $shop['city'] . $shop['area'] . $shop['locate'],
                    'desc'       => $desc,
                    'longitude'  => $longitude,
                    'latitude'   => $latitude,
                    'seller_id'  => $seller_id,
                    'status'     => 1,
                ], true);
                $station->update($stationid, [
                    'title' => $new_title,
                    'address' => $shop['province'] . $shop['city'] . $shop['area'] . $shop['locate']
                ]);

                $item =['id'=>$lbsid, 'sid'=>$shop_station_id, 'enable'=>1];
                $ret = lbsAPI::updatePOI($item);
                if ($ret['status'] != 0) {
                    //@todo 回滚部分数据
                    echo json_encode($ret);
                    exit;
                }
                $user->log_add_shop_station($uid, $shop_station_id, $shop_id, $stationid);
                LOG::DEBUG("station id: $stationid and new shop station: $shop_station_id and lbsid: $lbsid  bind to shop: $shop_id successs");
                echo json_encode($ret);
                exit;
                break;

            default:
                # code...
                break;
        }

        // 显示地图
        $ret = $station->fetch($stationid);
        if(! $ret) {
            echo "invalid param, no station";
            exit;
        }
        break;

    default:
}
