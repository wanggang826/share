<?php
include_once "table_common.php";
class table_jjsan_shop_station extends table_common
{
    const DISABLE = 0;
    const ENABLE  = 1;

    static $_t = 'jjsan_shop_station';
	public function __construct() {
		$this->_table = 'jjsan_shop_station';
		$this->_pk    = 'id';
		parent::__construct();
	}

	public function count_by_field($k,$v) {
		return DB::result_first('SELECT COUNT(*) FROM %t WHERE %i ', array($this->_table, DB::field($k, $v)));
	}

	public function fetch_by_field($k,$v) {
		return DB::fetch_first('SELECT * FROM %t WHERE %i ', array($this->_table, DB::field($k, $v)));
	}

	public function fetch_all_by_field($k, $v, $start = 0, $limit = 0, $status = [self::DISABLE, self::ENABLE]) {
		return DB::fetch_all('SELECT * FROM %t WHERE %i AND %i'.DB::limit($start, $limit), array($this->_table, DB::field($k, $v), DB::field('status', $status)));
	}

	public function fetch_all_by_conds($conds, $fields, $start = 0, $limit = 0) {
		$sql_conds = [$this->_table];
		if($fields && is_array($fields)) {
			$sql = "SELECT ";
			foreach($fields as $f) {
				$sql .= "`$f`,";
			}
			$sql = rtrim($sql, ',') . " FROM %t WHERE ";
		} else {
			$sql = "SELECT * FROM %t WHERE ";
		}
		if($conds && is_array($conds)) {
			foreach($conds as $k => $v) {
				$sql_conds[] = DB::field($k, $v);
				$sql .= "%i AND ";
			}
			$sql .= "1=1 ";
		} else {
			$sql .= "1=1 ";
		}
		$sql .= DB::limit($start, $limit);
		return DB::fetch_all($sql, $sql_conds);
	}

	public function getBriefAll() {
		return DB::fetch_all('select id,title from %t WHERE %i', [$this->_table, DB::field('title', '', '<>')]);
	}

	public function getField($id, $field) {
		$ret = DB::fetch_first("SELECT `{$field}` FROM %t WHERE %i ", array($this->_table, DB::field($this->_pk, $id)));
		return $ret[$field];
	}

    public function getFeeSettings($id, $type = 'array')
    {
        $fee_id = $this->getField($id, 'fee_settings');
        if(empty($fee_id)) {
            $fee_settings = C::t('common_setting')->fetch('jjsan_fee_settings');;
            if ($type == 'array') {
                return json_decode($fee_settings, 1);
            } elseif ($type == 'json') {
                return $fee_settings;
            }
        }
        $ret = ct('fee_strategy')->fetch($fee_id);
        if ($type == 'array') {
            return json_decode($ret['fee'], true);
        } elseif ($type == 'json') {
            return $ret['fee'];
        }
    }

	public function updateFields($sid, $data) {
		return DB::update($this->_table, $data, ['station_id'=>$sid]);
	}

	public function getTitle($id) {
		return $this->getField($id, 'title');
	}

    public function getFeeSettingsByStationId($stationId)
    {
		$rst = $this->where(['station_id' => $stationId])->first();
		$ret = ct('fee_strategy')->getStrategySettings($rst['fee_settings']);
        return $ret;
    }

    public function getPicSettingsByStationId($stationId)
    {
        $rst = $this->where(['station_id' => $stationId])->first();
        $ret = ct('pictext_settings')->fetch($rst['pictext_settings']);
        return $ret;
    }

    public function setFieldsById($id, array $data)
    {
        return DB::update($this->_table, $data, [$this->_pk=>$id]);
    }

	public function getErrorMans($sid = 0) {
		if ( !empty($sid) && is_int($sid) ) {
			$ret = DB::fetch_all('SELECT error_man FROM %t WHERE %i ', array($this->_table, DB::field('station_id', $sid)));
			return $ret['error_man'];
		} else {
			$ret = DB::fetch_all('SELECT error_man FROM %t WHERE %i ', array($this->_table, DB::field('error_man', '' , '<>')));
			return array_column($ret, 'error_man' );
		}
	}

    public function unBindShop($id)
    {
        return $this->setFieldsById($id, ['shopid' => 0]);
    }

	public function bindShop($id, $shop_id) {
		return $this->setFieldsById($id, ['shopid' => $shop_id]);
	}

	public function getCity($id)
    {
		$address = $this->getField($id, 'address');
		return substr($address, 0, strpos($address, '市') + 3);
	}

	public function getIdByStaionId($station_id) {
		$ret = DB::fetch_first('SELECT id FROM %t WHERE %i ', array($this->_table, DB::field('station_id', $station_id)));
		return $ret['id'];
	}
	/*
		查询某个城市下的所有站点
		@param address 城市地址
		@return [[商铺站点id, 站点标题]...]
	*/
	public function searchTitleByAddress($address, $device_ver = '', $lbsid = 'not all', $orderBy = 0)
	{
        $orderCond = $orderBy ? ' ORDER BY ' . DB::order('id') : '' ;
        $sql = "select a.id, a.title from %t as a join %t as b on a.station_id = b.id where a.address like %i and %i " . $orderCond;
        if ($device_ver === '') {
            $condition = [$this->_table, "jjsan_station", "'%".$address."%'", 1];
        } else {
            $condition = [$this->_table, "jjsan_station", "'%".$address."%'", DB::field('device_ver', $device_ver)];
        }
        $titles = DB::fetch_all($sql,$condition);
        return $titles;
     }

	 /***
      *  获取站点信息
      *  shop_sid 是商铺站点ID
      *  返回 station 与 shop 和合并数据
      *  id 为 shop_station
      *  station_id
      *  shopid
      */
    public function getInfo($shopStationId, $province = false)
    {
        $shopStationInfo = $this
            ->select('`id`, `shopid`, `station_id`, `lbsid`, `title`, `address`, `desc`, `longitude`, `latitude`')
            ->where(['id' => $shopStationId])
            ->first();
        $stationInfo = ct('station')->fetch($shopStationInfo['station_id']);
        if (empty($stationInfo)) return false;

        // 整合station信息到shopStation里面
        $shopStationInfo['empty'] = $stationInfo['empty'];
        $shopStationInfo['usable'] = $stationInfo['usable'];

        // 整合shop信息到shopStation里面
        if (empty($shopStationInfo['shopid'])) {
            $shopStationInfo['shop_name'] = '';
        } else {
            $shopInfo = ct('shop')->fetch($shopStationInfo['shopid']);
            $shopStationInfo['title'] = $shopInfo['name'];
            // 显示到  区 + 地址
            if(!$province){
                $shopStationInfo['address'] = $shopInfo['area'] . $shopInfo['locate'];
            }
            $shopStationInfo['shop_type'] = $shopInfo['type'];
            $shopStationInfo['shoplogo'] = $shopInfo['logo'];
            $shopStationInfo['shopcarousel'] = json_decode($shopInfo['carousel'], true);
        }
        return $shopStationInfo;
    }

 	/**
 	 *	获取所有站点信息
	 *	$shopStationIds 是商铺站点ID集合
	 *	返回 shop_station, station 与 shop 和合并数据
     *  isMerge
     *      true 合并同一个商铺下面的数据到第一个商铺站点
     *      false 不合并
 	*/
    public function getAllInfo($shopStationIds, $isMerge = false, $province = false)
 	{
        $ret = [];
        foreach($shopStationIds as $id){
            if ($info =  $this->getInfo($id, $province)) {
                $ret[$id] = $info;
            }
        }

        // shop_type logo
//        $shopTypeInfo = ct('shop_type')->get();
//        $newShopTypeInfo = [];
//        foreach($shopTypeInfo as $key => $value) {
//            $newShopTypeInfo[$value['id']] = $value['logo'];
//        }
        foreach($ret as &$v) {
            $v['shoplogo'] = $v['shoplogo'] ? json_decode($v['shoplogo'])[0] : '#'; // 没有logo就使用默认logo
        }

        if (!$isMerge) {
            return $ret;
        }

        // 合并shopid相同的商铺站点雨伞数量
        $shopIds = array_column($ret, 'shopid');
        $uniqueShopIds = array_unique($shopIds);
        $repeatShopIds = array_diff_assoc($shopIds, $uniqueShopIds);
        // 过滤shopid为0
        $repeatShopIds = array_filter($repeatShopIds);


        $singleInfos = []; // 使用id为key
        $repeatInfos = []; // 使用shopid为key，便于雨伞求和
        foreach($ret as &$v) {
            if (in_array($v['shopid'], $repeatShopIds)) {
                if (key_exists($v['shopid'], $repeatInfos)) {
                    $repeatInfos[$v['shopid']]['usable'] += $v['usable'];
                    $repeatInfos[$v['shopid']]['empty']  += $v['empty'];
                } else {
                    $repeatInfos[$v['shopid']] = $v;
                }
                $repeatInfos[$v['shopid']]['more'] = 1; // 标记为多商铺站点网点
            } else {
                $singleInfos[$v['id']] = $v;
            }
        }

        $newRepeatInfos = []; //使用id为key
        foreach ($repeatInfos as $vv) {
            $newRepeatInfos[$vv['id']] = $vv;
        }

        return array_merge($singleInfos, $newRepeatInfos);
 	}

    public function filter($shopStationIds, $mark = 0, $page_size)
    {
        $shops = [];
        $count = 0;
        if($mark){
            $shopStationIds = array_slice($shopStationIds, $mark);
        }
        foreach($shopStationIds as $id){
            if ($info =  $this->getInfo($id, true)) {
                $info['shoplogo'] = $info['shoplogo'] ? json_decode($info['shoplogo'])[0] : '#'; // 没有logo就使用默认logo
                if (key_exists($info['shopid'], $shops)) {
                    $shops[$info['shopid']]['usable'] += $info['usable'];
                    $shops[$info['shopid']]['empty'] += $info['empty'];
                    $shops[$info['shopid']]['more'] = 1;
                }else{
                    $count ++;
                    if($count == ($page_size + 1)){
                        $shops = array_values($shops);
                        return ['shops' => $shops, 'mark' => $mark];
                    }
                    $shops[$info['shopid']] = $info;
                    $shops[$info['shopid']]['lng'] = $info['longitude'];
                    $shops[$info['shopid']]['lat'] = $info['latitude'];
                }
            }
            $mark ++;
        }

        return ['shops' => $shops, 'mark' => $mark];
    }


    /**
     * 返回 desc,empty,usable,station_id 数据
     * @param $shopStationIds
     * @return mixed 2维数组
     */
    public function getPartInfoForApi($shopStationIds)
    {
        $shopStationInfo = $this->where(['id' => $shopStationIds])->get();
        $stationIds = array_column($shopStationInfo, 'station_id');
        $stationIds = array_filter($stationIds);
        $stationInfo = ct('station')->select('id, usable, empty')->where(['id' => $stationIds])->get();

        $data = array_map(function($a) use ($shopStationInfo) {
            foreach ($shopStationInfo as $v) {
                if ($v['station_id'] == $a['id']) {
                    $a['desc'] = $v['desc'];
                }
            }
            return $a;
        }, $stationInfo);
        return $data;
    }

    public function searchShopStation($conditions, $page, $pageSize, $accessCities = null, $accessShopes = null)
    {
        extract($conditions);

        $return = ['data' => '', 'count' => 0];
        $where = [];
        $anyWhere = [];


        // 查询条件 用 and 连接

        if (isset($status) && $status != -1) {
            $where['status'] = $status;
        }

        if ($province || $city || $area) {
            $where['address'] = ['value' => $province . $city . $area .'%', 'glue' => 'like'];
        }

        if ($keyword) {
            $where['title'] = ['value' => '%' . $keyword . '%', 'glue' => 'like'];
        }

        if ($sid) {
            $where['station_id'] = $sid;
        }

        // 授权城市,授权商铺, 用 or 连接
        if ($accessCities !== null && $accessShopes !== null) {
            // 城市和商铺都为空时
            if (empty($accessCities) && empty($accessShopes)) return $return;

            // 城市为空, 商铺不为空
            if (empty($accessCities) && $accessShopes) {
                $anyWhere[] = ['shopid' => $accessShopes];
            }
            // 商铺为空, 城市不为空
            // 城市传进来的是数组, 需要处理下
            if ($accessCities && empty($accessShopes)) {
                foreach ($accessCities as $v) {
                    $anyWhere[] = ['address' => ['value' => '%' . $v . '%', 'glue' => 'like']];
                }
            }
            // 二者都不为空时
            if ($accessCities && $accessShopes) {
                //城市传进来的是数组, 需要处理下
                $anyWhere[] = ['shopid' => $accessShopes];
                foreach ($accessCities as $v) {
                    $anyWhere[] = ['address' => ['value' => '%' . $v . '%', 'glue' => 'like']];
                }
            }
        }
        $return['data']  = $this->where($where)->anyWhere($anyWhere)->limit(($page-1)*$pageSize, $pageSize)->get();
        $return['count'] = $this->where($where)->anyWhere($anyWhere)->count();
        return $return;
 	}

    /**
     * 在已有商铺下新增商铺站点的时候，需要自动在商铺站点后增加字母，若已有ABC，则增加D
     * @param  $shop_id
     * @return string $letter
     */
    public function getNewTitleOfShopStation($shop_id)
    {
        $name = $this->select('title')->where(['shopid' => $shop_id])->order('id desc')->first()['title'];
        $old_letter = (substr($name, -1));
        $new_letter = chr(ord($old_letter) + 1);
        $new_title = substr($name, 0, -1) . $new_letter;
        return $new_title;
    }

    /**
     *	用户附近商铺信息
     *	@param 中心经度
     *	@param 中心纬度
     *	@return array | boolean 范围内的设备信息
     */

    public function shopsForMap($longitude, $latitude){
        if (empty($latitude) || empty($longitude)) {
            return false;
        }

        $shop_station_ids = getShopStationNearby($longitude, $latitude);
        if ($shop_station_ids) {
            $output = $this->getAllInfo($shop_station_ids, true);
            foreach ($output as $k => $v){
                $shop_station = ct('shop_station')->fetch($v['id']);
                $lng = $shop_station['longitude'];
                $lat = $shop_station['latitude'];
                $output[$k]['longitude'] = $lng;
                $output[$k]['latitude'] = $lat;
            }
            return $output;
        }elseif (empty($shop_station_ids)) {
            return [];
        }
        return false;
    }
}
