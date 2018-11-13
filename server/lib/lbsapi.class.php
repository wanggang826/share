<?php
require_once "scurl.class.php";

class lbsAPI {

    const COORD_TYPE = 3;
    const ENABLE = 1;

    public static function updatePOI($data) {
        if(!$data || !is_array($data))
            return false;

        $api = 'http://api.map.baidu.com/geodata/v3/poi/update';
        // $data中需带有唯一索引key
        $data['geotable_id'] = GEOTABLE_ID;
        $data['ak'] = BAIDU_MAP_AK;
        $curl = new sCurl( $api, 'POST', $data );
        $ret = json_decode( $curl->sendRequest(), true );
        if($ret['status'] != 0) {
            LOG::ERROR('baidu lbs update poi fail, ret:' . print_r($ret, true));
            $i = 1;
            do{
                LOG::WARN("update POI-----try $i times-----");
                $curl = new sCurl( $api, 'POST', $data );
                $ret = json_decode( $curl->sendRequest(), true );
                if ($ret['status'] == 0) {
                    LOG::INFO("update POI success");
                    break;
                } else {
                    LOG::INFO('baidu lbs update poi fail, ret:' . print_r($ret, true));
                }
                $i++;
            }while($i < 4);
        }
        return $ret;
    }

    public static function searchPOI($keyword, $city, $pageIndex, $pageSize) {
        $data = array(
            'q' => $keyword, // 检索关键字
            'page_index' => $pageIndex, // 页码
            'page_size' => $pageSize,
            'region' => $city, // 城市名
            'geotable_id' => GEOTABLE_ID, //test 117126, jjsan 119779
            'ak' => BAIDU_MAP_AK, // 用户ak
            'filter' => "enable:1", //已经启用的
        );

        $api = "http://api.map.baidu.com/geosearch/v3/local";
        // $data中需带有唯一索引key
        $curl = new sCurl( $api, 'GET', $data );
        $ret = json_decode( $curl->sendRequest(), true );
        if($ret['status'] != 0) {
            LOG::ERROR('baidu lbs search poi fail, ret:' . print_r($ret, true));
            $i = 1;
            do{
                LOG::WARN("search POI-----try $i times-----");
                $curl = new sCurl( $api, 'POST', $data );
                $ret = json_decode( $curl->sendRequest(), true );
                if ($ret['status'] == 0) {
                    LOG::INFO("search POI success");
                    break;
                } else {
                    LOG::INFO('baidu lbs search poi fail, ret:' . print_r($ret, true));
                }
                $i++;
            }while($i < 4);
        }
        return $ret;
    }

    public static function searchAllPOI($keyword, $city, $pageIndex, $pageSize) {
        $data = array(
            'q' => $keyword, // 检索关键字
            'page_index' => $pageIndex, // 页码
            'page_size' => $pageSize,
            'region' => $city, // 城市名
            'geotable_id' => GEOTABLE_ID, //test 117126, jjsan 119779
            'ak' => BAIDU_MAP_AK, // 用户ak
        );

        $api = "http://api.map.baidu.com/geosearch/v3/local";
        // $data中需带有唯一索引key
        $curl = new sCurl( $api, 'GET', $data );
        $ret = json_decode( $curl->sendRequest(), true );
        if($ret['status'] != 0) {
            LOG::ERROR('baidu lbs search poi fail, ret:' . print_r($ret, true));
            $i = 1;
            do{
                LOG::WARN("search all POI-----try $i times-----");
                $curl = new sCurl( $api, 'POST', $data );
                $ret = json_decode( $curl->sendRequest(), true );
                if ($ret['status'] == 0) {
                    LOG::INFO("search all POI success");
                    break;
                } else {
                    LOG::INFO('baidu lbs search all poi fail, ret:' . print_r($ret, true));
                }
                $i++;
            }while($i < 4);
        }
        return $ret;
    }

    public function createPOI($name, $latitude, $longitude, $address, $enable = self::ENABLE)
    {
        LOG::DEBUG("create poi data: name->$name, latitude->$latitude, longitude->$longitude, address->$address");
        $data = array(
            'title'       => $name,        // 充电站名称
            'latitude'    => $latitude,
            'longitude'   => $longitude,
            'coord_type'  => self::COORD_TYPE,
            'address'     => $address,
            'enable'      => $enable,
            'geotable_id' => GEOTABLE_ID,  // test 117126, jjsan 119779
            'ak'          => BAIDU_MAP_AK, // 用户ak
        );

        $api = "http://api.map.baidu.com/geodata/v3/poi/create";
        // $data中需带有唯一索引key
        $curl = new sCurl( $api, 'POST', $data );
        $ret = json_decode( $curl->sendRequest(), true );
        if($ret['status'] != 0) {
            LOG::ERROR('baidu lbs create poi fail, ret:' . print_r($ret, true));
            $i = 1;
            do{
                LOG::WARN("create POI-----try $i times-----");
                $curl = new sCurl( $api, 'POST', $data );
                $ret = json_decode( $curl->sendRequest(), true );
                if ($ret['status'] == 0) {
                    LOG::INFO("create POI success");
                    break;
                } else {
                    LOG::INFO('baidu lbs create poi fail, ret:' . print_r($ret, true));
                }
                $i++;
            }while($i < 4);
        }
        return $ret;
    }

    public static function aMapCoordinateConvert($baiduCoordinates)
    {
        $api = 'http://restapi.amap.com/v3/assistant/coordinate/convert';
        $data['key'] = GAODE_MAP_KEY_FOR_API;
        $data['locations'] = $baiduCoordinates;
        $data['coordsys'] = 'baidu';
        $curl = new sCurl( $api, 'GET', $data );
        $ret = json_decode( $curl->sendRequest(), true );
        return $ret;
    }

    public static function convertGps($location)
    {
        $api = 'http://api.map.baidu.com/geoconv/v1/';
        $data['ak'] = BAIDU_MAP_AK;
        $data['coords'] = $location;
        $curl = new sCurl($api, 'GET', $data);
        $ret = json_decode($curl->sendRequest(), true);
        return $ret;
    }
}