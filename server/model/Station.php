<?php
namespace model;

use \DB;
use \C;

class Station extends Model{

	public $total = 0;

	private $ids = [];

	private $station;
	private $umbrella;

	public function __construct()
	{
		$this->station = ct('station');
		$this->umbrella = ct('umbrella');
	}

	public function city($station_id)
	{
		return getStationCity($station_id);
	}

	public function update($data)
	{
		foreach($this->ids as $sid => $station){
            $this->station->update($sid,$data);
		}
	}

	public function get_one($sid)
	{
		$s =  $this->station->fetch_all($sid)[$sid];
		if(!$s) return false;
		$systemSettings = $jjsan_DEFAULT_SYSTEM_SETTINGS;
		$checkSyncTime = time()-$systemSettings['checkupdatedelay'];

		$s['colorcount'] = colorCount2HumanText($s['colorcount']);
		// 同步信息
		if($s['sync_time'] > $checkSyncTime)
			$s['network_status'] = 0;
		else
			$s['network_status'] = 1;

		// 商铺信息
		if($shop = ct('shop') -> fetch_all($s['shopid'])){
			$s["shopname"] = $shop[$s['shopid']]['name'];
			$s["shoplogo"] = json_decode($shop[$s['shopid']]['logo']);
			$s["shoplocate"] = $shop[$s['shopid']]['locate'];
			$s["shopcost"] = $shop[$s['shopid']]['cost'];
			$s["shopphone"] = $shop[$s['shopid']]['phone'];
			$s["shopstime"] = $shop[$s['shopid']]['stime'];
			$s["shopetime"] = $shop[$s['shopid']]['etime'];
			$s["shopcarousel"] = json_decode($shop[$s['shopid']]['carousel']);
		}else{
			$s['shopname'] = '';
		}
		if(!$s['title']){
			return;
		}
		$this -> ids[$sid] = $s;
		return $this -> ids[$sid];
	}

	/*
		获取所有站点信息
	*/
	public function get_all($ids)
	{
		foreach($ids as $key => $id){
			$this -> get_one($id);
		}
		return $this -> ids;
	}

	public function get_by($k,$v)
	{
		return $this -> station -> fetch_by_field($k,$v);
	}

	public function unbind_shop($sid,$shopid){
		return $this -> station -> update($sid,['shopid'=>0]);
	}

    /*
        管理员权限下的站点
        area : 区域数组
        sid : 绑定的站点数组
    */
    public function station_under_access($city_arr = [], $shop_id_arr = [] ,$city = '', $station_id = '' , $keyword = '', $adapterstatus = '' , $slotstatus = '' , $status = '',$dev_version = '',$umbrella_outside_sync = '',$page = 1 , $page_size = 10 ){
        $return = ['data' => [],'count' => ''];
        $station_table = ct('station');
        $sql = " from ".DB::table('jjsan_station')." as s left join ".DB::table('jjsan_shop_station')." as ss on s.id = ss.station_id  left join ".DB::table('jjsan_shop')." as sh on ss.shopid = sh.id";

        $sql .= " where 1 = 1 ";

        // 管理员拥有区域权限 ???
        $access = '';
        $citys = $city_arr ? "'".join("','",$city_arr)."'" : 0;
        $shop_ids = $shop_id_arr ? "'".join("','",$shop_id_arr)."'" : 0;
        if($city_arr || $shop_id_arr){
            // 拥有城市权限
            if($city_arr){
                $access = " and sh.city in ($citys) and ss.shopid != 0";
            }
            // 拥有商铺权限
            if($shop_id_arr){
                $access = " and ss.shopid in ($shop_ids) and ss.shopid != 0 ";
            }
            // 两种权限都有
            if($city_arr && $shop_id_arr){
                $access = " and ( ss.shopid in ($shop_ids) or sh.city in ($citys) ) and ss.shopid != 0";
            }
        // 两种权限都没有
        }else{
            return $return;
        }
        $sql .= $access;

        // 管理员权限下城市查询
        if($city){
            // 绑定的商铺
            if($city == "shop"){
                $sql .= " and sh.id in ($shop_ids) ";
            // 查询某城市下
            }else{
                $sql .= " and sh.city = '$city' ";
            }
        }

        // 关键字查询
        if($keyword){
            $sql .= " and s.title like '%$keyword%' ";
        }

        // 特定 station_id　查询
        if($station_id){
            $sql .= " and s.id = '$station_id' ";
        }

        // 机器版本
        if(isset($dev_version) && $dev_version != '-1'){
            $sql .= " and s.device_ver = '$dev_version' ";
        }

        // 雨伞模组状态
        if(isset($slotstatus) && $slotstatus == 0){
            $sql .= " and s.slotstatus = 0 ";
        }elseif($slotstatus == 1){
            $sql .= " and s.slotstatus > 0 ";
        }

        // 网络状态
        $miniSycnCheck = time() - $station_table->getNetworkCheckInterval(DEVICE_MINI_1);
        $bigSycnCheck  = time() - $station_table->getNetworkCheckInterval(DEVICE_1);
        $mini_vers = join(',',getMiniDeviceVers());

        if(isset($status) && $status != 2){
            $sign = ($status == 1) ? '>' : '<';
            $sql .= " and (s.sync_time $sign '$miniSycnCheck' AND s.device_ver IN($mini_vers)) OR (s.sync_time $sign '$bigSycnCheck' AND s.device_ver NOT IN($mini_vers)) ";
        }

        $start = ($page - 1) * $page_size;

        $sql_count = "select count(s.id) as count ".$sql; // 查询总数

        $sql .= " order by s.id desc limit $start,$page_size "; //　分页
        $sql = "select s.*,sh.name,sh.city ".$sql;

        $stations =  DB::fetch_all($sql);
        foreach($stations as &$s){
            $s['colorcount'] = colorCount2HumanText($s['colorcount']);
            $s['network_status'] = $station_table -> isOnline($s['sync_time'],$s['device_ver']);
        }
        error_log($sql);
        $return['count'] = DB::fetch_all($sql_count)[0]['count'];
        $return['data']  = $stations;
        return $return;
    }

    public function searchStation($conditions, $page, $pageSize, $accessStation = null)
    {
        extract($conditions);

        $return = ['data' => '', 'count' => 0];
        $where = [];

        ### 权限条件用 and 连接
        if ($accessStation !== null) {
            if (empty($accessStation)) return $return;
            $anyWhere[] = ['id' => $accessStation];
        }

        ### 查询条件 用 and 连接

        // 网络状态 0断线 1在线
        if (isset($status) && $status != -1) {
            // 获取判断不在线的时间值
            $intervalTime = time() - $this->station->getNetworkCheckInterval();
            if ($status == 0) {
                $where['sync_time'] = ['value' => $intervalTime, 'glue' => '<'];
            } else {
                $where['sync_time'] = ['value' => $intervalTime, 'glue' => '>'];
            }
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

        // 机器硬件版本
        if (isset($device_ver) && !empty($device_ver)) {
            $where['device_ver'] = $device_ver;
        }

        // 机器软件版本
        if (isset($soft_ver) && !empty($soft_ver)) {
            $where['soft_ver'] = $soft_ver;
        }

        // 雨伞模组状态
        if(isset($slotstatus) && $slotstatus != -1){
            if ($slotstatus == 0) $where['slotstatus'] = 0;
            if ($slotstatus == 1) $where['slotstatus'] = ['value' => 0, 'glue' => '>'];
        }

        // 电池状态
        if(isset($isdamage) && $isdamage != -1){
            if ($isdamage == 0) $where['isdamage'] = 0;
            if ($isdamage == 1) $where['isdamage'] = 1;
            if ($isdamage == 2) $where['isdamage'] = 2;
            if ($isdamage == 3) $where['isdamage'] = 3;
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
            $stationIds = $this->umbrella->getAllStationIdsWithUmbrellaSync();
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

        $return['data'] = $this->station
            ->where($where)
            ->anyWhere($anyWhere, 'and', 'and')
            ->order($orderBy)
            ->limit(($page -1) * $pageSize, $pageSize)
            ->get();
        $return['count'] = $this->station
            ->where($where)
            ->anyWhere($anyWhere, 'and', 'and')
            ->count();
        return $return;
    }

    public function isStationHasumbrellaSync($stationId)
    {
        $rst = $this->umbrella->where(['station_id' => $stationId, 'status' => UMBRELLA_OUTSIDE_SYNC])->count();
        return $rst['status'] ? true: false;
    }

    public function getStationSetting($stationId)
    {
        return $this->station->getField($stationId, 'station_setting_id');
    }
    public function setStationSettings($stationId, $strategy_id)
    {
        return $this->station->setSyncStrategy($stationId,$strategy_id);
    }


    public function getEnableStatus($stationId)
    {
        return $this->station->getEnableStatus($stationId);
    }

    public function isStationOnline($stationId)
    {
        return $this->station->checkNetworkOnline($stationId);
    }
}
