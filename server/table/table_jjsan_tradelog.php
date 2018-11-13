<?php
include_once "table_common.php";
class table_jjsan_tradelog extends table_common
{
    static $_t = 'jjsan_tradelog';

    public function __construct()
    {
        $this->_table = 'jjsan_tradelog';
        $this->_pk = 'orderid';
        parent::__construct();
    }

    // 租借成功订单
    public static $borrow_success_status = [
        ORDER_STATUS_RENT_CONFIRM,  // 2 借出
        ORDER_STATUS_TIMEOUT_NOT_RETURN,    // 92 未归还(押金扣完)
        ORDER_STATUS_LOSS, // 100 (用户登记遗失)
        ORDER_STATUS_RETURN,        // 3 归还
        ORDER_STATUS_TIMEOUT_CANT_RETURN,   // 98 归还(押金扣完)
        ORDER_STATUS_RETURN_EXCEPTION_SYS_REFUND,   // 94 异常归还(借出后同步,系统自动归还)
        ORDER_STATUS_RETURN_EXCEPTION_MANUALLY_REFUND,  // 93 借出成功,归还失败
    ];

    // 归还成功订单
    public static $return_success_status = [
        ORDER_STATUS_RETURN,        // 3 归还
        ORDER_STATUS_TIMEOUT_NOT_RETURN,    // 92 未归还(押金扣完)
        ORDER_STATUS_LOSS, // 100 (用户登记遗失)
        ORDER_STATUS_TIMEOUT_CANT_RETURN,   // 98 归还(押金扣完)
        ORDER_STATUS_RETURN_EXCEPTION_MANUALLY_REFUND,  // 93 归还失败, 管理员手动退押金
        ORDER_STATUS_RETURN_EXCEPTION_SYS_REFUND,   // 94 异常归还(借出后同步,系统自动归还)
    ];

    public function fetch_all_by_status($status, $start, $limit)
    {
        $status = $status >= 0 ? 'WHERE status=' . intval($status) : '';
        return DB::fetch_all("SELECT * FROM %t %i ORDER BY lastupdate DESC LIMIT %d, %d", array($this->_table, $status, $start, $limit));
    }

    public function fetch_all_by_tag($tag, $start, $limit)
    {
        $tag = $tag >= 0 ? 'WHERE tag=' . intval($tag) : '';
        return DB::fetch_all("SELECT * FROM %t %i ORDER BY lastupdate DESC LIMIT %d, %d", array($this->_table, $tag, $start, $limit));
    }

    public function get_average_time($shopStation_id, $begin_time, $end_time)
    {
        $res = DB::fetch_all('SELECT borrow_time, return_time FROM %t WHERE %i AND %i AND %i', array(
                $this->_table,
                DB::field('return_shop_station_id', $shopStation_id),
                DB::field('return_time', $end_time, '<='),
                DB::field('return_time', $begin_time, '>=')
            )
        );

        $rent_time = [];

        foreach ($res as $value) {
            $rent_time[] = $value['return_time'] - $value['borrow_time'];
        }
        return humanTime((int)(array_sum($rent_time) / count($rent_time)));
    }

    public function getField($orderid, $field)
    {
        $ret = DB::fetch_first("SELECT `{$field}` FROM %t WHERE %i ", array($this->_table, DB::field($this->_pk, $orderid)));
        return $ret[$field];
    }

    public function count_by_field($k, $v)
    {
        return DB::result_first('SELECT COUNT(*) FROM %t WHERE %i ', array($this->_table, DB::field($k, $v)));
    }

    public function fetch_by_field($k, $v)
    {
        return DB::fetch_first('SELECT * FROM %t WHERE %i ', array($this->_table, DB::field($k, $v)));
    }

    public function getLatestOrderByBorrowStation($sid)
    {
        return DB::fetch_first('SELECT ' . $this->_pk . ', status FROM %t WHERE %i ORDER BY lastupdate DESC', array($this->_table, DB::field('borrow_station', $sid)));
    }

    public function getOrderStatus($orderId)
    {
        return DB::fetch_first('SELECT ' . $this->_pk . ', status FROM %t WHERE %i', array($this->_table, DB::field($this->_pk, $orderId)));
    }

    public function getPayOrdersByStatusAndTag($id, $status, $tag)
    {
        // 选择订单顺序：先未退过的订单，再价格高的订单
        return DB::fetch_all('SELECT * FROM %t WHERE %i AND %i AND %i AND refundno>=0 ORDER BY refunded ASC, price DESC', array($this->_table, DB::field('uid', $id), DB::field('status', $status, 'notin'), DB::field('tag', $tag)));
    }

    public function fetch_all_by_field($field, $value, $orderfield)
    {
        foreach ($orderfield as $k => $v) {
            $_orderfield[] = DB::order($k, $v);
        }
        $_orderfield = implode(",", $_orderfield);
        if (!empty($value) && is_array($value)) {
            foreach ($value as $k => $v) {
                $_field[] = DB::field($field, $v);
            }
            $_field = implode(" or ", $_field);
            return DB::fetch_all("SELECT * FROM %t WHERE %i ORDER BY " . $_orderfield, array($this->_table, $_field));
        }


    }

    private function create_start_time($date)
    {
        $start_time = strtotime($date . ' 00:00:00');
        return $start_time;
    }

    private function create_end_time($date)
    {
        $end_time = strtotime($date . ' 23:59:59');
        return $end_time;
    }

    public function fetch_day_machine_data($date, $station_id, $output = 'report')
    {
        $day_start = $this->create_start_time($date);
        $day_end = $this->create_end_time($date);
        if ($station_id == 0) {
            return DB::fetch_all('SELECT borrow_time,borrow_shop_station_id,usefee FROM %t WHERE %i AND %i AND %i', array('jjsan_tradelog',
                DB::field('borrow_time', $day_start, '>='),
                DB::field('borrow_time', $day_end, '<='),
                DB::field('borrow_shop_station_id', 0, '>')));
        } elseif (is_array($machine_id)) {
            return DB::fetch_all('SELECT borrow_time,borrow_shop_station_id,usefee FROM %t WHERE %i AND %i AND %i', array('jjsan_tradelog',
                DB::field('borrow_time', $day_start, '>='),
                DB::field('borrow_time', $day_end, '<='),
                DB::field('borrow_shop_station_id', $machine_id, 'in')));
        } else {
            return DB::fetch_all('SELECT borrow_time,borrow_shop_station_id,usefee FROM %t WHERE %i AND %i AND %i', array('jjsan_tradelog',
                DB::field('borrow_time', $day_start, '>='),
                DB::field('borrow_time', $day_end, '<='),
                DB::field('borrow_shop_station_id', $station_id)));
        }
    }

    public function fetch_all_by_station($borrow_shop_station_id)
    {
        if ($borrow_shop_station_id == 0) {
            return DB::fetch_all('SELECT borrow_time,borrow_shop_station_id,usefee FROM %t WHERE %i AND %i', array('jjsan_tradelog', DB::field('borrow_shop_station_id', 0, '>'), DB::field('tag', 111)));
        } elseif (is_array($borrow_shop_station_id)) {
            return DB::fetch_all('SELECT borrow_time,borrow_shop_station_id,usefee FROM %t WHERE %i AND %i', array('jjsan_tradelog', DB::field('borrow_shop_station_id', $borrow_shop_station_id, 'in'), DB::field('tag', 111)));
        } else {
            return DB::fetch_all('SELECT borrow_time,borrow_shop_station_id,usefee FROM %t WHERE %i AND %i', array('jjsan_tradelog', DB::field('borrow_shop_station_id', $borrow_shop_station_id), DB::field('tag', 111)));
        }
    }

    public function order_count_by_uid($uid)
    {
        return DB::result_first('SELECT count(*) FROM %t WHERE %i', [
            $this->_table,
            DB::field('uid', $uid)
        ]);
    }

    public function getAllOrderInfoByShopStationId($shopStationId, $beginTime, $endTime)
    {
        return DB::fetch_all('SELECT  FROM %t WHERE %i AND %i AND %i AND %i', [
            $this->_table,
            DB::field('borrow_time', $beginTime, '>'),
            DB::field('borrow_time', $endTime, '<='),
            DB::field('borrow_shop_station_id', $shopStationId)
        ]);
    }

    public function usefee_count_by_uid($uid)
    {
        return DB::result_first('SELECT sum(usefee) FROM %t WHERE %i', [
            $this->_table,
            DB::field('uid', $uid)
        ]);
    }

    public function outstanding_order_count($uid)
    {
        return DB::result_first("SELECT count(*) FROM %t WHERE %i AND %i", [
            $this->_table,
            DB::field('uid', $uid),
            DB::field('status', ORDER_STATUS_RENT_CONFIRM)
        ]);
    }

    public function getBorrowSuccessOrderInfo($beginTime, $endTime, $shopStationId)
    {
        return DB::result_first('SELECT count(*) FROM %t WHERE %i AND %i AND %i AND %i', [
            $this->_table,
            DB::field('borrow_time', $beginTime, '>'),
            DB::field('borrow_time', $endTime, '<='),
            DB::field('borrow_shop_station_id', $shopStationId),
            DB::field('status', self::$borrow_success_status)
        ]);
    }

    public function fetch_all_borrow_success_order($beginTime, $endTime, $query_stations)
    {
        return DB::result_first('SELECT count(*) FROM %t WHERE %i AND %i AND %i AND %i', [
            $this->_table,
            DB::field('borrow_time', $beginTime, '>'),
            DB::field('borrow_time', $endTime, '<='),
            DB::field('borrow_shop_station_id', $query_stations),
            DB::field('status', self::$borrow_success_status)
        ]);
    }

    public function fetch_all_return_success_order($beginTime, $endTime, $query_stations)
    {
        return DB::result_first('SELECT count(*) FROM %t WHERE %i AND %i AND %i AND %i', [
            $this->_table,
            DB::field('return_time', $beginTime, '>'),
            DB::field('return_time', $endTime, '<='),
            DB::field('return_shop_station_id', $query_stations),
            DB::field('status', self::$return_success_status)
        ]);
    }

    public function fetch_all_borrow_try_user($beginTime, $endTime, $query_stations)
    {
        return DB::result_first('SELECT count(*) FROM %t WHERE %i AND %i AND %i AND %i GROUP BY openid, borrow_shop_station_id', [
            $this->_table,
            DB::field("borrow_time", $beginTime, '>'),
            DB::field("borrow_time", $endTime, "<="),
            DB::field('borrow_shop_station_id', $query_stations),
            DB::field("status", 0, ">")
        ]);
    }

    public function fetch_all_borrow_try_order($beginTime, $endTime, $query_stations)
    {
        return DB::result_first('SELECT count(*) FROM %t WHERE %i AND %i AND %i', [
            $this->_table,
            DB::field('borrow_time', $beginTime, '>'),
            DB::field('borrow_time', $endTime, '<='),
            DB::field('borrow_shop_station_id', $query_stations),
        ]);
    }


    public function fetch_all_borrow_success_user($beginTime, $endTime, $query_stations)
    {
        return DB::result_first('SELECT count(*) FROM %t WHERE %i AND %i AND %i AND %i GROUP BY openid, borrow_shop_station_id', [
            $this->_table,
            DB::field('borrow_time', $beginTime, '>'),
            DB::field('borrow_time', $endTime, '<='),
            DB::field('borrow_shop_station_id', $query_stations),
            DB::field('status', self::$borrow_success_status)
        ]);
    }

    public function fetch_all_return_success_user($beginTime, $endTime, $query_stations)
    {
        return DB::result_first('SELECT count(*) FROM %t WHERE %i AND %i AND %i AND %i GROUP BY openid, return_shop_station_id', [
            $this->_table,
            DB::field('return_time', $beginTime, '>'),
            DB::field('return_time', $endTime, '<='),
            DB::field('return_shop_station_id', $query_stations),
            DB::field('status', self::$return_success_status)
        ]);
    }

    public function fetch_all_total_usefee($beginTime, $endTime, $query_stations)
    {
        return DB::fetch_first('SELECT IFNULL(SUM(usefee),0) as total_usefee FROM %t WHERE %i AND %i AND %i AND %i',
            array('jjsan_tradelog',
                DB::field('return_time', $beginTime, '>'),
                DB::field('return_time', $endTime, '<='),
                DB::field('return_shop_station_id', $query_stations),
                DB::field('status', 0, '>')
            ))['total_usefee'];
    }

    public function fetch_all_timeout_order($beginTime, $endTime, $query_stations)
    {
        return DB::result_first('SELECT count(*) FROM %t WHERE %i AND %i AND %i AND %i', [
            $this->_table,
            DB::field('return_time', $beginTime, '>'),
            DB::field('return_time', $endTime, '<='),
            DB::field('return_shop_station_id', $query_stations),
            DB::field('status', [ORDER_STATUS_TIMEOUT_NOT_RETURN, ORDER_STATUS_LOSS]),
        ]);
    }

    public function fetch_all_charge_order($beginTime, $endTime, $query_stations)
    {
        return DB::result_first('SELECT count(*) FROM %t WHERE %i AND %i AND %i AND %i AND %i', [
            $this->_table,
            DB::field('return_time', $beginTime, '>'),
            DB::field('return_time', $endTime, '<='),
            DB::field('return_shop_station_id', $query_stations),
            DB::field('status', 0, '>'),
            DB::field('usefee', 0, '>'),
        ]);
    }

    public function fetch_all_return_all_order($beginTime, $endTime, $query_stations)
    {
        return DB::result_first('SELECT count(*) FROM %t WHERE %i AND %i AND %i AND (%i OR (%i AND %i))', [
            $this->_table,
            DB::field('return_time', $beginTime, '>'),
            DB::field('return_time', $endTime, '<='),
            DB::field('return_shop_station_id', $query_stations),
            DB::field('usefee', 0, '>'),
            DB::field('status', ORDER_STATUS_RETURN),
            DB::field('usefee', 0),
        ]);
    }

    public function fetch_all_return_try_order($beginTime, $endTime, $query_stations)
    {
        return DB::result_first('SELECT count(*) as all_return_try_order FROM %t WHERE %i AND %i AND %i', [
            $this->_table,
            DB::field('return_time', $beginTime, '>'),
            DB::field('return_time', $endTime, '<='),
            DB::field('return_station', $query_stations),
        ]);
    }

    public function fetch_all_time($beginTime, $endTime, $query_stations)
    {
        return DB::fetch_first('SELECT sum(borrow_time) as all_borrow_time,sum(return_time) as all_return_time,count(*) as order_count FROM %t WHERE %i AND %i AND %i AND %i',
            array('jjsan_tradelog',
                DB::field('return_time', $beginTime, '>'),
                DB::field('return_time', $endTime, '<='),
                DB::field('return_shop_station_id', $query_stations),
                DB::field('status', self::$return_success_status),
            ));
    }

    public function calulate_different_return_shop_station_id_rate($beginTime, $endTime, $query_stations)
    {
        $different_return_shop_station_id_order = DB::result_first('SELECT count(*) FROM %t WHERE %i AND %i AND %i AND %i AND %i', [
            $this->_table,
            DB::field('return_time', $beginTime, '>'),
            DB::field('return_time', $endTime, '<='),
            DB::field('return_shop_station_id', $query_stations),
            DB::field('borrow_shop_station_id', $query_stations, '<>'),
            DB::field('status', self::$return_success_status),
        ]);
        $all_return_order = $this->fetch_all_return_success_order($beginTime, $endTime, $query_stations);
        return round($different_return_shop_station_id_order / $all_return_order, 4) * 100 . '%';
    }

    public function fetch_all_pay_100_count($beginTime, $endTime, $query_stations)
    {
        return DB::result_first('SELECT count(*) FROM %t WHERE %i AND %i AND %i AND (%i OR %i)', [
            $this->_table,
            DB::field('borrow_time', $beginTime, '>'),
            DB::field('borrow_time', $endTime, '<='),
            DB::field('borrow_shop_station_id', $query_stations),
            DB::field('message', '%s:4:"paid";i:100%', 'like'),
            DB::field('message', '%s:4:"paid";d:100%', 'like'),
        ]);
    }

    public function fetch_all_pay_100_request_count($beginTime, $endTime, $query_stations)
    {
        $where .= " AND a.borrow_time > $beginTime";
        $where .= " AND a.borrow_time <= $endTime";
        $where .= " AND a.borrow_shop_station_id = $query_stations";
        $where .= ' AND a.message like \'%s:4:"paid";i:100%\'';
        $where .= ' OR a.message like \'%s:4:"paid";d:100%\'';
        // $where .= isset($platform) ? ' AND a.platform = ' . $platform : '';
        $orderby = " ORDER BY a.borrow_time DESC";
        $sqlfrom = " INNER JOIN `" . DB::table('jjsan_refund_log') . "` t ON t.uid=a.uid";
        $orderdata = DB::fetch_all('SELECT a.* FROM ' . DB::table('jjsan_tradelog') . " a " . $sqlfrom . $where . $orderby);
        $data = DB::result_first('SELECT count(*) FROM ' . DB::table('jjsan_tradelog') . " a " . $sqlfrom . $where);
        return $data;
    }

    public function fetch_all_pay_100_count_user($beginTime, $endTime, $query_stations)
    {
        $orderby = ' GROUP BY uid';
        $uid_array = DB::fetch_all('SELECT uid FROM %t WHERE %i AND %i AND %i AND (%i OR %i)' . $orderby,
            array('jjsan_tradelog',
                DB::field('borrow_time', $beginTime, '>'),
                DB::field('borrow_time', $endTime, '<='),
                DB::field('borrow_shop_station_id', $query_stations),
                DB::field('message', '%s:4:"paid";i:100%', 'like'),
                DB::field('message', '%s:4:"paid";d:100%', 'like'),
            ));
        return array_column($customer_array, 'customer');
    }

    public function increse_station_order_user($beginTime, $endTime, $query_stations)
    {
        $orderby = ' GROUP BY uid';
        return DB::result_first('SELECT count(*) FROM %t WHERE %i AND %i AND %i AND %i AND %i' . $orderby, [
            $this->_table,
            DB::field('borrow_time', $beginTime, '>'),
            DB::field('borrow_time', $endTime, '<='),
            DB::field('borrow_shop_station_id', $query_stations),
            DB::field('status', array()),
            DB::field('message', '%s:4:"paid";d:100%', 'like'),
        ]);
    }

    // 查询某些站点下当天的订单用户
    // $date 某日期
    // $stattion  eg . ['34','32'] 站点id数组
    // return $users; ['242','32545'] 用户id数组
    public function users($date, $station = '')
    {
        if (count($station) == 0) {
            return [];
        }
        $str = join($station, ',');
        $sql = "SELECT DISTINCT uid from " . DB::table('jjsan_tradelog') . " where '{$date}' = FROM_UNIXTIME(borrow_time,'%Y-%m-%d') AND sceneid in ({$str}) ";
        $users = DB::fetch_all($sql);
        $ret = [];
        if ($users) {
            foreach ($users as $u) {
                $ret[] = $u['uid'];
            }
        }
        return $ret;
    }

    public function get_borrowing_umbrella($borrow_station, $return_umbrella_id = false)
    {
        if ($return_umbrella_id) {
            $messages = DB::fetch_all('SELECT message FROM %t WHERE %i AND %i',
                array('jjsan_tradelog',
                    DB::field('borrow_shop_station_id', $borrow_station),
                    DB::field('status', [ORDER_STATUS_RENT_CONFIRM, ORDER_STATUS_RENT_CONFIRM_FIRST]),
                ));
            $messages_array = array_column($messages, 'message');
            $umbrella_ids = array();
            // $inside_umbrella_ids = ct('umbrella')->getUmbrellaCountBySid($borrow_station, true);
            foreach ($messages_array as $message) {
                $message = unserialize($message);
                $umbrella_ids[] = $message['umbrella'];
            }
            return $umbrella_ids;
        }
        return DB::result_first('SELECT count(*) FROM %t WHERE %i AND %i', [
            $this->_table,
            DB::field('borrow_shop_station_id', $borrow_station),
            DB::field('status', [ORDER_STATUS_RENT_CONFIRM, ORDER_STATUS_RENT_CONFIRM_FIRST]),
        ]);
    }

    /**
     * $conditions 数组详解
     *
     * b_device_ver     借出机器版本
     * b_shop_sid       借出商铺站点id
     * r_device_ver     归还机器版本
     * r_shop_sid       归还商铺站点id
     * order_id         订单号id
     * umbrella_id       雨伞id
     * filter_fee       过滤0费用
     * usefeeSituation  多种费用情况
     * user_id          用户id
     * user_openid      openid模糊查询
     * city             城市
     * b_start_time     借出日期起始
     * b_end_time       借出日期结束
     * r_start_time     归还日期起始
     * r_end_time       归还日期结束
     * b_sid            借出机器id
     * r_sid            归还机器id
     * b_shop_sid       借出商铺id
     * r_shop_sid       归还商铺id
     * platform         订单来源
     * status           订单状态
     * err_status       错误状态
     */
    public function search_order($conditions, $page, $pageSize, $accessCities, $accessShops)
    {
        // tag暂时不用了，因为现在只有1种商品
        //$strsql = "SELECT * FROM " . DB::table('jjsan_tradelog') . " WHERE tag = " . DB::quote(TAG_UMBRELLA) . ' AND ';

        // 计数前缀
        $cntPre = "SELECT COUNT(*) ";
        // 数据前缀
        $dataPre = "SELECT * ";

        // sql语句组合
        $strsql =  "FROM " . DB::table('jjsan_tradelog') . " WHERE 1 AND ";
        $return = ['data' => [], 'count' => ''];
        extract($conditions);


        //订单号	用户ID/openid 雨伞ID	支付方式	订单状态	费用	订单来源
        //借出商铺站点ID/名称/机器ID/时间
        //归还商铺站点ID/名称/机器ID/时间


        // 授权城市 和 授权商铺 同时存在时使用anyWhere
        if ($accessShops !== null || $accessCities !== null) {
            if ($accessCities && $accessShops) {
                $strsql .= DB::field('borrow_city', $accessCities) . " OR " . DB::field('borrow_shop_id', $accessShops) . " AND ";
            } else {
                // 授权城市
                if ($accessCities) {
                    $strsql .= DB::field('borrow_city', $accessCities) . " AND ";
                }

                // 授权商铺
                if ($accessShops) {
                    $strsql .= DB::field('borrow_shop_id', $accessShops) . " AND ";
                }

                // 没有任何授权
                if (empty($accessShops) && empty($accessCities)) {
                    return $return;
                }
            }
        }

        // 借出城市查询
        if (!empty($city)) {
            $strsql .= DB::field('borrow_city', $city) . " AND ";
        }

        // 借出机器版本
        if (isset($b_device_ver) && $b_device_ver != '-1') {
            $strsql .= DB::field('borrow_device_ver', $b_device_ver) . " AND ";
        }

        //　归还机器版本
        if (isset($r_device_ver) && $r_device_ver != '-1') {
            $strsql .= DB::field('return_device_ver', $r_device_ver) . " AND ";
        }

        //　订单号查询
        if (!empty($order_id)) {
            $strsql .= "`orderid` LIKE " . DB::quote("%$order_id%") . " AND ";
        }

        // 雨伞id查询
        if (!empty($umbrella_id)) {
            $strsql .= DB::field('umbrella_id', $umbrella_id) . " AND ";
        }

        // 费用区间
        if (isset($usefeeSituation) && $usefeeSituation != '-1') {
            if ($usefeeSituation == '=0') {
                $strsql .= "`usefee` = 0 AND ";
            } elseif ($usefeeSituation == '>0') {
                $strsql .= "`usefee` > 0  AND ";
            } elseif ($usefeeSituation == '>=4') {
                $strsql .= "`usefee` >= 4 AND ";
            } elseif ($usefeeSituation == '>=10') {
                $strsql .= "`usefee` >= 10 AND ";
            }
        }

        // 用户查询
        if (!empty($user_id)) {
            $strsql .= DB::field('uid', $user_id) . " AND ";
        }

        // 用户openid查询
        if (!empty($user_openid)) {
            $strsql .= DB::field('openid', $user_openid) . " AND ";
        }

        // 查询借出机器id
        if (!empty($b_sid)) {
            $strsql .= DB::field('borrow_station', $b_sid) . " AND ";
        }

        // 查询归还机器id
        if (!empty($r_sid)) {
            $strsql .= DB::field('return_station', $r_sid) . " AND ";

        }

        // 查询借出商铺站点名称
        if (!empty($b_shop_station_title)) {
            $strsql .= "`borrow_station_name` like " . DB::quote("%$b_shop_station_title%") . " AND ";
        }

        // 查询归还商铺站点名称
        if (!empty($r_shop_station_title)) {
            $strsql .= "`return_station_name` like " . DB::quote("%$r_shop_station_title%") . " AND ";
        }

        // 订单来源
        if (isset($platform) && $platform != '-1') {
            $strsql .= DB::field('platform', $platform) . " AND ";
        }

        // 订单状态
        if (isset($status) && $status != '-1') {
            switch ($status) {
                case ORDER_LIST_ALL_BORROW :
                    $strsql .= "`status` in " . '(' . ORDER_STATUS_RENT_CONFIRM . ',' . ORDER_STATUS_RETURN . ')' . " AND ";
                    break;

                case ORDER_LIST_EXCEPTION :
                    $strsql .= "`status` not in " . '(' . ORDER_STATUS_RENT_CONFIRM . ',' . ORDER_STATUS_RETURN . ',' . ORDER_STATUS_RENT_CONFIRM_FIRST . ')' . " AND ";
                    break;

                default :
                    $strsql .= DB::field('status', $status) . " AND ";
            }
        }

        // 订单错误状态
        if (isset($err_status) && $err_status != '-1') {
            $strsql .= DB::field('status', $err_status) . " AND ";
        }


        if (!empty($b_start_time && $b_end_time)) {
            $stime = strtotime($b_start_time);
            $etime = strtotime($b_end_time);
            $strsql .= "`borrow_time` between " . DB::quote($stime) . " AND " . DB::quote($etime) . " AND ";
        } else {
            // 查询借出日期　(起始)
            if (!empty($b_start_time)) {
                $time = strtotime($b_start_time);
                $strsql .= DB::field('borrow_time', $time, '>=') . " AND ";
            }

            // 查询借出日期 (结束)
            if (!empty($b_end_time)) {
                $time = strtotime($b_end_time);
                $strsql .= DB::field('borrow_time', $time, '<=') . " AND ";
            }
        }

        if (!empty($r_start_time && $r_end_time)) {
            $stime = strtotime($r_start_time);
            $etime = strtotime($r_end_time);
            $strsql .= "`return_time` between " . DB::quote($stime) . " AND " . DB::quote($etime) . " AND ";
        } else {
            // 查询归还日期 (起始)
            if (!empty($r_start_time)) {
                $time = strtotime($r_start_time);
                $strsql .= DB::field('return_time', $time, '>=') . " AND ";
            }

            // 查询归还日期 (结束)
            if (!empty($r_end_time)) {
                $time = strtotime($r_end_time);
                $strsql .= DB::field('return_time', $time, '<=') . " AND ";
            }
        }

        // 计数
        $strsql = substr_replace($strsql, '', -4, 3);
        $count = DB::result_first($cntPre . $strsql);

        // 查询并返回结果
        $strsql = $strsql . 'ORDER BY ' . DB::order('borrow_time', 'DESC') . DB::limit((($page - 1) >= 0 ? $page - 1 : 0) * $pageSize, $pageSize);
        $data = DB::fetch_all($dataPre . $strsql);
        $return = ['data' => $data, 'count' => $count];
        return $return;

    }

    public function update_tradelog_by_orderid($orderid, $data)
    {
        return DB::query('UPDATE %t SET %i , %i , %i , %i , %i, %i, %i, %i, %i WHERE %i', [
            $this->_table,

            DB::field('umbrella_id', $data['umbrella_id']),
            DB::field('borrow_shop_id', $data['borrow_shop_id']),
            DB::field('return_shop_id', $data['return_shop_id']),
            DB::field('borrow_city', $data['borrow_city']),
            DB::field('return_city', $data['return_city']),
            DB::field('borrow_device_ver', $data['borrow_device_ver']),
            DB::field('return_device_ver', $data['return_device_ver']),
            DB::field('borrow_shop_station_id', $data['borrow_shop_station_id']),
            DB::field('return_shop_station_id', $data['return_shop_station_id']),

            DB::field('orderid', $orderid)
        ]);
    }

    public function deleteNotPaidOrder()
    {
        return DB::query('DELETE FROM %t WHERE %i AND %i', [
            $this->_table,
            DB::field('status', ORDER_STATUS_WAIT_PAY),
            DB::field('lastupdate', (time() - 3600), '<')
        ]);
    }

    /*
        订单更新幂等性检查, 保证多次请求的结果和一次请求的结果是一致的
        即短时间内并发多次更新, 只能有一次更新是有效的, 防止多次更新造成的一系列错误问题
        解决由于前端多次触发或由于网络重试导致的多次更新问题
        通过lastupdate的更新锁来实现, 3s内并发的请求均视为同一个请求
        可用于支付回调,借出确认,归还等等订单更新的场景
        返回是否合法, 即可是否可继续往下执行
    */
    public function idempotent($orderid)
    {
        return DB::query('UPDATE %t SET %i WHERE %i AND %i', [
            $this->_table,
            DB::field('lastupdate', time()),
            DB::field('orderid', $orderid),
            DB::field('lastupdate', time() - 3, '<')
        ]);
    }

    /*
		判断该设备当前是否有正在借出
		1. 是否有订单处于已支付未借出 1
		2. 是否有订单处于准备借出中, 且准备借出中的时间距离现在不超过20s
		如果都没有以上两种状态, 则该设备无正在借出的订单, 可让后来用户使用
		true, 有正在借出的订单
		false, 无正在借出的订单
	*/
    public function hasBorrowingOrder($stationId)
    {
        $count = DB::result_first('SELECT COUNT(*) FROM %t WHERE %i AND %i AND ((%i) OR (%i AND %i))', [
            $this->_table,
            DB::field('borrow_station', $stationId),
            DB::field('tag', TAG_UMBRELLA),
            DB::field('status', ORDER_STATUS_PAID),
            DB::field('status', ORDER_STATUS_RENT_CONFIRM_FIRST),
            DB::field('borrow_time', time() - 20, '>'),
        ]);
        return !empty($count);
    }

    /**
     * @param $uid
     * @return bool
     *
     * 已支付订单，借出第一次确认，借出订单，借出未拿走中间态（后台还未取消订单）  全部返回true
     * 或者
     * 遗失订单（后台或用户手动）且更新时间在2分钟之内的订单（后台还未结算订单） 返回true
     *
     * 其他返回false
     */
    public function hasUnfinishedZhimaOrder($uid)
    {
        $ret = DB::result_first('SELECT uid FROM %t WHERE %i AND %i AND ((%i AND %i) OR (%i AND %i)) limit 1', [
            $this->_table,
            // 公共条件
            DB::field('uid', $uid),
            DB::field('platform', PLATFORM_ZHIMA),
            // 条件1
            DB::field('status', [ORDER_STATUS_PAID, ORDER_STATUS_RENT_CONFIRM_FIRST, ORDER_STATUS_RENT_CONFIRM, ORDER_STATUS_RENT_NOT_FETCH_INTERMEDIATE]),
            DB::field('borrow_time', time() - 20 * 24 * 3600, '>'), //限制搜索20天以内的订单，因为超过20天，钱都扣完了
            // 条件2
            DB::field('status', [ORDER_STATUS_TIMEOUT_NOT_RETURN, ORDER_STATUS_LOSS]),
            DB::field('lastupdate', time() - 120, '>')
        ]);
        return $ret;
    }

    // 获取用户充值及支付金额信息
    public function walletDetail($uid)
    {
        return DB::query('SELECT `borrow_time`, `return_time`, `paid`, `usefee` FROM %t WHERE (%i OR %i) AND %i', [
            $this->_table,
            DB::field('paid', 0, '>'),
            DB::field('usefee', 0, '>'),
            DB::field('uid', $uid),
        ]);
    }

    /**
     *    获取用户租借记录相关数据
     * @param 用户id
     */
//    public function getUserOrders($uid)
//    {
//        // 支付完成, 借出第一次确认, 借出
//        $orderBorrowingStatus = [
//            ORDER_STATUS_PAID,
//            ORDER_STATUS_RENT_CONFIRM,
//            ORDER_STATUS_RENT_CONFIRM_FIRST,
//        ];
//
//        // 正常归还, 租金已扣完且用户已经归还, 押金扣完未归还, 借出未拿走, 超时自动退款,
//        // 上一单未完成, 机器借出故障, 机器没有雨伞, 电量不足, 同步时间失败, 终端网络超时,
//        // 借出后同步, 管理员后台手动撤销订单退回押金, 管理员后台手动退押金(部分或者全部)
//        $orderFinishedStatus = [
//            ORDER_STATUS_RETURN,
//            ORDER_STATUS_TIMEOUT_CANT_RETURN,
//            ORDER_STATUS_TIMEOUT_NOT_RETURN,
//            ORDER_STATUS_RENT_NOT_FETCH,
//            ORDER_STATUS_TIMEOUT_REFUND,
//
//            ORDER_STATUS_LAST_ORDER_UNFINISHED,
//            ORDER_STATUS_MOTOR_ERROR,
//            ORDER_STATUS_NO_UMBRELLA,
//            ORDER_STATUS_POWER_LOW,
//            ORDER_STATUS_SYNC_TIME_FAIL,
//            ORDER_STATUS_NETWORK_NO_RESPONSE,
//
//            ORDER_STATUS_RETURN_EXCEPTION_SYS_REFUND,
//            ORDER_STATUS_RETURN_MANUALLY,
//            ORDER_STATUS_RETURN_EXCEPTION_MANUALLY_REFUND,
//        ];
//
//        $where['status'] = array_merge($orderBorrowingStatus, $orderFinishedStatus);
//        $where['tag'] = TAG_UMBRELLA;
//        $where['uid'] = $uid;
//        $where['is_type'] = 0;
//
//        $orderdata = ct('tradelog')
//            ->where($where)
//            ->select('orderid, status, borrow_time, return_time, borrow_station_name, return_station_name, usefee, lastupdate')
//            ->order('lastupdate desc')
//            ->limit(0, 10)
//            ->get();
//        $orderdata = array_map(function ($a) {
//            $feeSettings = ct('tradeinfo')->getField($a['orderid'], 'fee_strategy');
//            $a['feeStr'] = makeFeeStr($feeSettings);
//            // 特殊处理：押金扣完未归还的订单
//            if ($a['status'] == ORDER_STATUS_TIMEOUT_NOT_RETURN) {
//                $a['return_station_name'] = '无';
//            }
//            return $a;
//        }, $orderdata);
//
//        if ($orderdata) {
//            $data = [
//                "code" => 0,
//                "msg" => "成功",
//                "data" => [
//                    "orders" => $orderdata,
//                ],
//            ];
//            return $data;
//        } elseif (empty($orderdata)) {
//            $data = [
//                "code" => 0,
//                "msg" => "成功",
//                "data" => [
//                    "orders" => [],
//                ],
//            ];
//            return $data;
//        }
//
//        return false;
//    }

    // 获取用户订单记录
    public function getUserOrders($uid, $page, $page_size)
    {
        // 订单分类 1为使用中，2为已完成，3为已关闭
        $using = [
            ORDER_STATUS_RENT_CONFIRM,
            //ORDER_STATUS_PAID,
            //ORDER_STATUS_RENT_CONFIRM_FIRST 前端不展示未拿走的订单，所以支付完成和第一次确认不展示到前端
        ];
        $completed = [
            ORDER_STATUS_RETURN,
            ORDER_STATUS_RETURN_EXCEPTION_MANUALLY_REFUND,
            ORDER_STATUS_RETURN_EXCEPTION_SYS_REFUND,
            ORDER_STATUS_TIMEOUT_CANT_RETURN
        ];
        $closed = [ORDER_STATUS_TIMEOUT_NOT_RETURN, ORDER_STATUS_LOSS];

        $condition = array_merge($using, $completed, $closed);

        $orderdata = DB::fetch_all('SELECT * FROM %t WHERE %i AND %i AND %i ORDER BY %i %i', array(
            $this->_table,
            DB::field('uid', $uid),
            DB::field('tag', TAG_UMBRELLA),
            DB::field('status', $condition),
            DB::order('borrow_time', 'DESC'),
            DB::limit($page, $page_size)
        ));

        $orders = [];
        if ($orderdata) {
            foreach ($orderdata as $order) {
                $return_time = $order['return_time'] ? date('Y-m-d H:i:s', $order['return_time']) : null;

                //租借时长
                $time2 = empty($order['return_time']) ? time() : $order['return_time'];
                $timediff = $time2 - $order['borrow_time'];

                //获取收费策略
                $fee_strategy = ct('tradeinfo')->getField($order['orderid'], 'fee_strategy');
                $usefee = $order['usefee'];

                $status = in_array($order['status'], $using) ? 1 : (in_array($order['status'], $completed) ? 2 : 3);
                $orders[] = array(
                    'orderid'        => $order['orderid'],
                    'status'         => $status,
                    'borrow_time'    => date('Y-m-d H:i:s', $order['borrow_time']),
                    'borrow_station' => $order['borrow_station'],
                    'last_time'      => humanTime($timediff),
                    'return_time'    => $return_time,
                    'borrow_name'    => $order['borrow_station_name'],
                    'return_name'    => $order['return_station_name'],
                    'use_fee'        => $usefee,
                    'price'          => $order['price'],
                    'fee_strategy'   => makeFeeStr($fee_strategy),
                    'is_zhima'       => $order['platform'] == PLATFORM_ZHIMA ? 1 : 0,
                );
            }
        }
        return $orders;
    }

    // 获取用户单条订单数据
    public function getOrderDataForApi($orderId)
    {
        // 订单分类 1为使用中，2为已完成，3为已关闭
        $using = [
            ORDER_STATUS_RENT_CONFIRM,
            //ORDER_STATUS_PAID,
            //ORDER_STATUS_RENT_CONFIRM_FIRST 前端不展示未拿走的订单，所以支付完成和第一次确认不展示到前端
        ];
        $completed = [
            ORDER_STATUS_RETURN,
            ORDER_STATUS_RETURN_EXCEPTION_MANUALLY_REFUND,
            ORDER_STATUS_RETURN_EXCEPTION_SYS_REFUND,
            ORDER_STATUS_TIMEOUT_CANT_RETURN
        ];
        $closed = [ORDER_STATUS_TIMEOUT_NOT_RETURN, ORDER_STATUS_LOSS];

        $order = $this->fetch($orderId);

        $return_time = $order['return_time'] ? date('Y-m-d H:i:s', $order['return_time']) : null;

        //租借时长
        $time2 = empty($order['return_time']) ? time() : $order['return_time'];
        $timediff = $time2 - $order['borrow_time'];

        //获取收费策略
        $fee_strategy = ct('tradeinfo')->getField($order['orderid'], 'fee_strategy');
        $usefee = $order['usefee'];

        $status = in_array($order['status'], $closed) ? 3 : (in_array($order['status'], $completed) ? 2 : 1);
        $data = [
            'orderid'        => $order['orderid'],
            'status'         => $status,
            'borrow_time'    => date('Y-m-d H:i:s', $order['borrow_time']),
            'borrow_station' => $order['borrow_station'],
            'last_time'      => humanTime($timediff),
            'return_time'    => $return_time,
            'borrow_name'    => $order['borrow_station_name'],
            'return_name'    => $order['return_station_name'],
            'use_fee'        => $usefee,
            'price'          => $order['price'],
            'fee_strategy'   => makeFeeStr($fee_strategy),
            'is_zhima'       => $order['platform'] == PLATFORM_ZHIMA ? 1 : 0,
        ];
        return $data;
    }

    // 判断用户是否有未查看的未归还订单
    public function unreturn($uid)
    {
        return DB::result_first('SELECT count(*) FROM %t WHERE %i AND %i', [
            $this->_table,
            DB::field('uid', $uid),
            DB::field('status', ORDER_STATUS_RENT_CONFIRM)
        ]);
    }
}


