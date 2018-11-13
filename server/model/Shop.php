<?php
namespace model;

use \C;
use \Exception;

class Shop{

	private $ids = [];

	private $shop;

	private $count;

	private $station;

	private $shop_station;

	public function __construct()
	{
		$this -> shop = ct('shop');
		$this -> station = ct('station');
		$this -> shop_station = ct('shop_station');
	}

	public function add($con)
	{
		$con_key = ['name','locate','cost','phone','stime','etime','logo','carousel','type','province','city','area'];
		$this -> _check_must_key($con,$con_key);
		foreach($con_key as $key){
			if(!is_string($con[$key])){
				$data[$key] = json_encode($con[$key]);
				continue;
			}
			$data[$key] = $con[$key];
		}
		return ct('shop') -> insert($data,true);
	}

	public function get($ids)
	{
		$this -> _reset();
		if(is_numeric($ids)){
			$this -> ids[$ids] = [];
		}
		$this -> fetch_data();
		return $this -> ids;
	}

	public function fetch_data()
	{
		foreach($this -> ids as $id => &$shop)
		{
			$shops = $this -> shop -> fetch_all($id);
			if($shops){
				$shop = $shops[$id];
			}
		}
	}

	public function update($con)
	{
		$this -> ids = $con;
		return $this -> _update();
	}

	private function _update(){
		foreach($this -> ids as $id => $shop){
			$update_data = [];
			$keys = ['name','locate','cost','phone','stime','etime','logo','carousel','type','province','city','area'];
			foreach($keys as $key){
				if(isset($shop[$key])){
					$update_data[$key] = $shop[$key];
				}
			}
			return $this -> shop -> update($id,$update_data);
		}
	}

	private function _reset()
	{
		$this -> ids = [];
	}

	/*
		检查传参数组是否包含必须有的键值对
	*/
	public static function _check_must_key($con,$key_arr)
	{
		foreach($key_arr as $key){
			if(!isset($con[$key])){
				throw new Exception("少传了必须传的参数");
				return ;
			}
		}
		return true;
	}

	// 返回数据表中商品总数
	public function count()
	{
		$this -> count = $this -> shop -> count();
		return $this->count;
	}
    

	// 获取某个商铺下的所有站点
	public function stations($shopid){
		return $this -> station -> fetch_all_by_field('shopid', $shopid);
	}

	public function shop_stations($shopid, $start, $limit, $status){
		return $this -> shop_station -> fetch_all_by_field('shopid', $shopid, $start, $limit, $status);
	}

	// 获取站点的设置
    public function getStationSettingsIds($stationId)
    {
        // 站点信息
        $station = $this->station->fetch($stationId);
        // 商铺站点信息
        $shop_station = $this->shop_station->where(['station_id' => $stationId])->first();
        return [
            'fee_setting_id' => $shop_station['fee_settings'],
            'station_setting_id' => $station['station_setting_id']
        ];

    }

	// 获取站点的全部信息
    public function getStationsInfo($conditions, $page, $pageSize, $accessStation)
    {
        extract($conditions);

        $return = ['data' => '', 'count' => 0];
        $where = [];

        ### 权限条件用 and 连接
        if ($accessStation !== null) {
            if (empty($accessStation)) return $return;
            $where['id'] = $accessStation;
        }

        ### 查询条件 用 and 连接

        // 网络状态
        if (isset($status) && $status != -1) {
            $where['status'] = $status;
        }

        // 省市区
        if ($province || $city || $area) {
            //直辖市去掉省份
            if ($province == $city) $province = '';
            $where['address'] = ['value' => $province . $city . $area .'%', 'glue' => 'like'];
        }

        // 站点id
        if ($sid) {
            $where['id'] = $sid;
        }

        // 站点名称
        if ($keyword) {
            $where['title'] = ['value' => '%' . $keyword . '%', 'glue' => 'like'];
        }

        // 机器版本
        if (isset($dev_version) && $dev_version != -1) {
            $where['device_ver'] = $dev_version;
        }

        // 雨伞模组状态
        if(isset($slotstatus) && $slotstatus != -1){
            if ($slotstatus == 0) $where['slotstatus'] = 0;
            if ($slotstatus == 1) $where['slotstatus'] = ['value' => 0, 'glue' => '>'];
        }

        // 排序
        if ($orderby) {
            if ($orderby == 1) $orderBy = 'heartbeat_rate';
            if ($orderby == 2) $orderBy = 'power_on_time';
        }
        if ($orderBy) {
            if ($order_desc == 1) {
                $orderBy .= ' desc';
            } else {
                $orderBy .= ' asc';
            }
        }

        // 借出后同步雨伞 需要关联umbrella表
        // 1. 在umbrella表中获取有同步雨伞的stationId
        // 2. 在station表中查询时只在这些stationId查询
        if ($umbrella_outside_sync == 'on') {
            $stationIds = ct('umbrella')->getAllStationIdsWithumbrellaSync();
            // 如果为空 说明没有站点异常
            if (empty($stationIds)) return $return;
            if ($where['id']) {
                // 如果搜索条件中含义stationId 且 该id不在$stationIds中, 说明没有站点异常
                if (!in_array($where['id'], $stationIds)) return $return;
                // 该id在$stationIds中:$where['id'] = $sid;
            } else {
                $where['id'] = $stationIds;
            }
        }

        // 站点信息
        $stations = $this->station
            ->where($where)
            ->order($orderBy)
            ->limit(($page - 1) * $pageSize, $pageSize)
            ->get();
        $count = $this->station->where($where)->count();

        // 站点IDs
        $stationIds = array_map(function($a){
            return $a['id'];
        }, $stations);

        // 商铺站点信息
        $shop_stations = $this->shop_station->where(['station_id' => $stationIds])->get();
        $shopIds = array_map(function($a){
            return $a['shopid'];
        }, $shop_stations);
        $new_shop_stations = [];
        foreach ($shop_stations as $v) {
            $new_shop_stations[$v['id']] = $v;
        }

        // 商铺信息
        $shops = $this->shop->where(['id' => $shopIds])->get();
        $new_shops = [];
        foreach ($shops as $v) {
            $new_shops[$v['id']] = $v;
        }

        // 收费策略信息
        $feeIds = array_map(function($a){
            return $a['fee_settings'];
        }, $shop_stations);
        $fee_settings = ct('fee_strategy')->where(['id' => $feeIds])->get();
        $new_fee_settings = [];
        foreach ($fee_settings as $v) {
            $new_fee_settings[$v['id']] = $v;
        }

        // 配置策略信息
        $settingIds = array_map(function($a){
            return $a['station_setting_id'];
        }, $stations);
        $station_settings = ct('station_settings')->where(['id' => $settingIds])->get();
        $new_station_settings = [];
        foreach ($station_settings as $v) {
            $new_station_settings[$v['id']] = $v;
        }

        // 将shop_stations中的shopid,error_man,fee_settings 整合到stations里面去
        $stations = array_map(function($a) use ($new_shop_stations){
            $a['shopid'] = $new_shop_stations[$a['id']]['shopid'] ? : 0;
            $a['error_man'] = $new_shop_stations[$a['id']]['error_man'];
            $a['fee_settings'] = $new_shop_stations[$a['id']]['fee_settings'];
            return $a;
        }, $stations);

        // 整合站点信息
        $stations = array_map(function($a) use ($new_fee_settings, $new_station_settings, $new_shops) {
            // 维护人员
            $a['error_man'] = json_decode($a['error_man'], true);
            // 收费策略
            $a['fee_settings_name'] = $new_fee_settings[$a['fee_settings']]['name'];
            // 配置策略
            $a['settings_name'] = $new_station_settings[$a['station_setting_id']]['name'];
            // 商铺名称
            $a['shopname'] = $new_shops[$a['shopid']]['name'];
            return $a;
        }, $stations);

        return ['data' => $stations, 'count' => $count];
    }

    /**
     * 不允许通过sid来更新收费策略等信息，商铺中更新收费策略等信息应该是更新此商铺下所有shop_station的信息
     */

    public function getStationIdsByShopIds($shopIds)
    {
        $rst = $this->shop_station->where(['shopid' => $shopIds])->get();
        return array_map(function($a){
            return $a['station_id'];
        }, $rst);
    }

    public function getShopInfoByStationId($stationId)
    {
        $shopStation = $this->shop_station->where(['station_id' => $stationId])->first();
        if (!$shopStation) return false;
        $shop = $this->shop->fetch($shopStation['shopid']);
        return $shop;
    }

    public function getStationSettingsNameByStationId($stationId)
    {
        $station = $this->station->fetch($stationId);
        if (!$station) return false;
        $feeSettings = ct('station_settings')->fetch($station['station_setting_id']);
        return $feeSettings['name'];
    }
}
