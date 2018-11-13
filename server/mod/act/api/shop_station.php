<?php
use model\Api;
use model\User;

// 管理员区域　导航
include_once JJSAN_DIR_PATH . 'mod/area_nav_tree.inc.php';

$station      = ct('station');
$shopStation  = ct('shop_station');

// 实例化用户模型
$user = new User();

switch($opt){

    # 更换站点标识ID
    case 'shop_station_replace':
        // 检查2个场景ID是否有相应的站点
        $originStationInfo = $station->fetch($stationid);
        $newStationInfo = $station->fetch($new_stationid);
        if (!$originStationInfo || !$newStationInfo) {
            Api::output([], 1, '不存在该机器');
            break;
        }

        // 检查待绑定的站点ID是否已绑定其它商铺
        $count = $shopStation->where(['station_id' => $new_stationid])->count();
        if ($count) {
            Api::output([], 2, '该机器已绑定其他商铺站点');
            break;
        }

        LOG::INFO("shop station replace, origin station id: $stationid , new station id: $new_stationid");

        // @todo 这里应该要用事务
        // 0.查询当前站点绑定的商铺站点信息
        // 1.把这个商铺站点绑定到新的机器上
        // 2.把新机器的信息（title,address）与这个商铺站点同步
        // 3.把原来的机器信息（title,address）置空
        $shopStationInfo = $shopStation->where(['station_id' => $stationid])->first();
        $res = $shopStation->update($shopStationInfo['id'], ['station_id' => $new_stationid])
            && $station->update($new_stationid, ['title' => $shopStationInfo['title'], 'address' => $shopStationInfo['address']])
            && $station->update($stationid, ['title' => '', 'address' => '']);
        if ($res) {
            $user->log_shop_station_replace($uid, $shopStationInfo['id'], $originStationInfo['id'], $newStationInfo['id']);
            LOG::INFO("shop station replace success");
            Api::output([], 0, '换机成功');
        } else {
            LOG::WARN("shop station replace fail");
            Api::output([], 1, '换机失败');
            break;
        }
        break;

    # 撤掉站点
    case 'shop_station_remove':
        $shopStationInfo = $shopStation->where(['station_id' => $stationid])->first();
        $data = ['enable' => 0];
        $ret = updateStationToLBS($shopStationInfo['lbsid'], $data);
        if ($ret['errcode'] == 0) {
            $res = $shopStation->update($shopStationInfo['id'], ['station_id' => 0, 'status' => 0])
                && $station->update($stationid, ['title' => '', 'address' => '']);
        }
        LOG::INFO("update station lbs , " . print_r($ret, 1));
        if ($res) {
            $user->log_station_remove($uid, $shopStationInfo['id'], $stationid);
            LOG::INFO("shop station remove success");
            Api::output([], 0, '撤机成功');
        } else {
            LOG::WARN("shop station remove fail");
            Api::output([], 1, '撤机失败');
            break;
        }
        break;

    case 'get_province':
        $provinces = array_map(function($v){
            return $v['province'];
        }, $area_nav_tree);
        Api::output($provinces, 0, 'success');
        break;

    case "get_area_info":
        if ($ajax == 1) {
            if ($province) {
                if ($city) {
                    Api::output(getAreasByCity($province, $city, $area_nav_tree));
                } else {
                    Api::output(getCitiesByProvince($province, $area_nav_tree));
                }
            }
            exit;
        }
        break;

    default:
        Api::output([], 1, 'unknown request');

}

