<?php
define('IN_MOBILE', 2);
if(IN_MOBILE == 1) {
    dheader("location: " . "{$_SERVER['PHP_SELF']}?{$_SERVER['QUERY_STRING']}&mobile=2");
    exit;
}

switch ($act) {
    case 'list':
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

    case 'change_coordinates':
        $data['key'] = GAODE_MAP_KEY_FOR_API;
        $data['locations'] = "$lng,$lat";
        $data['coordsys'] = "baidu";
        $data['output'] = "JSON";
        $api = "http://restapi.amap.com/v3/assistant/coordinate/convert";
        $scurl = new sCurl( $api, 'GET', $data );
        $ret = $scurl->sendRequest();
        echo $ret;
        exit;


    case 'map':
        include template('jjsan:activity/map');
        exit;

    default:
        exit;
}

