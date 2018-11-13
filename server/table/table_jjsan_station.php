<?php
include_once "table_common.php";
class table_jjsan_station extends table_common
{
    const STATION_HEARTBEAT_MISSING_NUMBER = 5; //连续5个心跳包没有判断站点离线

    static $_t = 'jjsan_station';
	private $_shop_station = NULL;


	public function __construct() {
		$this->_table = 'jjsan_station';
		$this->_pk    = 'id';
		$this->_shop_station = ct('shop_station');
		parent::__construct();
	}

	public function count_by_field($k,$v) {
		return DB::result_first('SELECT COUNT(*) FROM %t WHERE %i ', array($this->_table, DB::field($k, $v)));
	}

	public function fetch_by_field($k,$v) {
		return DB::fetch_first('SELECT * FROM %t WHERE %i ', array($this->_table, DB::field($k, $v)));
	}

	public function fetch_all_by_field($k, $v, $start = 0, $limit = 0) {
		return DB::fetch_all('SELECT * FROM %t WHERE %i '.DB::limit($start, $limit), array($this->_table, DB::field($k, $v)));
	}

	public function getBriefAll() {
		return DB::fetch_all('select id,title from %t WHERE %i', [$this->_table, DB::field('title', '', '<>')]);
	}

	public function getField($sid, $field) {
		$ret = DB::fetch_first("SELECT `{$field}` FROM %t WHERE %i ", array($this->_table, DB::field($this->_pk, $sid)));
		return $ret[$field];
	}
    
	public function checkInventory($sid, $colorCount) {
		$colors = $this->getAllColorCount($sid);
		foreach ($colorCount as $c=>$val) {
			$cid = mapUmbrellaColor($c);
			if($colors[$cid] < $val) {
				return false;
			}
		}
		return true;
	}

	public function getTitle($sid) {
		return $this->getField($sid, 'title');
	}

	public function getSlotsStatus($sid) {
		$ret = $this->fetch($sid);
		$slotStatus = str_split($ret['slotstatus']);
		$rst = [];
		for ($i = 0; $i < $ret['total']; $i++) {
		    $rst[$i] = $slotStatus[$i];
        }
        return $rst;
	}

    public function getFeeSettings($sid, $type = 'array') {
		$ret = DB::fetch_first('SELECT fee_settings FROM %t WHERE %i ', array($this->_table, DB::field($this->_pk, $sid)));
		$ret = ct('fee_strategy')->getStrategySettings($ret['fee_settings']);
		if ($type == 'array') {
        	return json_decode($ret['fee'], true);
		} elseif ($type == 'json') {
			return $ret['fee'];
		}
    }

    public function getStationSettings($sid)
    {
        $station  = $this->fetch($sid);
        if ($station['station_setting_id'] > 0) {

        } else {

        }
    }

	/*
		查询某个城市下的所有站点
		@param address 城市地址
		@return [[站点id, 站点标题]...]
	*/
	public function searchTitleByAddress($address, $device_ver = '', $lbsid = 'not all', $orderBy = 0)
	{
        $orderCond = $orderBy ? ' ORDER BY ' . DB::order('id') : '' ;
        $sql = "select id,title from %t where address like %i and %i " . $orderCond;
        if ($device_ver === '') {
            $condition = [$this->_table,"'%".$address."%'", 1];
        } else {
            $condition = [$this->_table,"'%".$address."%'", DB::field('device_ver', $device_ver)];
        }
        $titles = DB::fetch_all($sql,$condition);
        return $titles;
     }

	/*
		设置同步策略
	*/
	public function setSyncStrategy($station_id,$strategy_id)
	{
		return parent::update($station_id,['station_setting_id'=>$strategy_id]);
	}

	/*
		获取同步策略
	*/
	public function getSyncStrategy($station_id)
	{
		return parent::fetch($station_id,true)['station_setting_id'];
	}

	public function getStationStrategy($station_id, $device_ver)
	{
		$strategy_id = $this -> getSyncStrategy($station_id);
		if($strategy_id){
			$systemSettings = json_decode(ct('station_settings') -> getSetting($strategy_id)['settings'],true);
		}else{
			$systemSettings = json_decode( C::t('common_setting')->fetch('jjsan_system_settings' . ($device_ver ? : '')), true );
		}
		if(! $systemSettings) {
			global $jjsan_DEFAULT_SYSTEM_SETTINGS;
			$systemSettings = $jjsan_DEFAULT_SYSTEM_SETTINGS;
		}
		return $systemSettings;
	}

	public function getAllDeviceVer()
	{
		$list = DB::fetch_all('SELECT distinct(device_ver) FROM %t' . ' ORDER BY device_ver',
			array($this->_table));
		return array_column($list, 'device_ver');
	}


	public function checkNetworkOnline($stationId) {
		$station = $this->fetch($stationId);
		$settings = C::t('#jjsan#jjsan_station_settings')->getUsingSetting($station['station_setting_id']);
		if (time() - $station['sync_time'] > $settings['heartbeat'] * self::STATION_HEARTBEAT_MISSING_NUMBER) {
		    return false;
        } else {
		    return true;
        }
    }


	// ========== shop_station ==============//
	public function getErrorMans($sid) {
		return $this->_shop_station->getErrorMans($sid);
	}

	/*
	   获取商铺站点ID
	*/
	public function getShopStationId($sid) {
		$ret = $this->_shop_station->fetch_all_by_conds(['station_id'=>$sid], ['id']);
		if($ret[0]) {
			return $ret[0]['id'];
		}
		return 0;
	}

	private function _hex2bin($hex) {
		$hex = base_convert($hex, 16, 2);
		// 高位补足
		if(8-strlen($hex) > 0) {
			$hex = str_repeat("0", 8-strlen($hex)) . $hex;
		}
		return $hex;
	}

    public function searchStation($conditions, $page, $pageSize, $accessStation = null)
    {
        extract($conditions);

        $return = ['data' => '', 'count' => 0];
        $where = [];

        // 权限条件用 and 连接
        if ($accessStation !== null) {
            if (empty($accessStation)) return $return;
            $where['id'] = $accessStation;
        }


        // 查询条件 用 and 连接

        // 网络状态
        if (isset($status) && $status != -1) {
            $where['status'] = $status;
        }

        // 省市区
        if ($province || $city || $area) {
            $where['address'] = ['value' => $province . $city . $area .'%', 'glue' => 'like'];
        }

        // 站点id
        if ($sid) {
            $where['station_id'] = $sid;
        }

        // 站点名称
        if ($keyword) {
            $where['title'] = ['value' => '%' . $keyword . '%', 'glue' => 'like'];
        }

        // 机器版本
        if (isset($dev_version) && $dev_version != -1) {
            $where['device_ver'] = $dev_version;
        }

        // 排序
        if ($orderby) {
            if ($orderby == 1) $orderBy = 'heartbeat_rate';
            if ($orderby == 2) $orderBy = 'heartbeat_rate';
        }
        if ($orderBy) {
            if ($order_desc == 1) {
                $orderBy .= ' desc';
            } else {
                $orderBy .= ' asc';
            }
        }
        if($page && $pageSize){
            $return['data'] = $this->where($where)->order($orderBy)->limit(($page -1) * $pageSize, $pageSize)->get();
            $return['count'] = $this->where($where)->order($orderBy)->count();
        } else {
            $return['data'] = $this->where($where)->order($orderBy)->get();
        }
        return $return;
	}


    public function getEnableStatus($sid)
    {
        return $this->_shop_station->select('id')->where(['station_id' => $sid, 'status' => 1])->get();
    }

    public function getNetworkCheckInterval()
    {
        return self::STATION_HEARTBEAT_MISSING_NUMBER * STATION_HEARTBEAT;
    }
}
