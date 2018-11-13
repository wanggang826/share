<?php
use model\User;

/************************自定义全局变量*********************/
$platform_arr    = [ 0 => '微信',1 => '支付宝',2 => '全部']; // 支付平台数组
$status_arr 	 = [0 => '全部',1 => '借出中',2 => '已归还']; // 租借状态
$count           = 0; // 总记录条数
$url             = "/index.php?mod=$mod&act=$act";
$show_days       = 7; // 默认显示一周的数据
$oneday          = 24 * 60 * 60; // 一天转换成秒数
$today           = strtotime(date('Y-m-d',time())) + $oneday - 1;// 当天时间 :  23 : 59 : 59


/************************用户输入***************************/
$role_selected   = isset($role_selected) ? $role_selected : -1; // 默认全部角色用户
$platform        = isset($platform) ? $platform : 2;  // 默认平台　全部
$id              = isset($id) ? $id : '';
$city            = isset($city) ? $city : '';
$etime           = !empty($etime) ? strtotime(date('Y-m-d',strtotime($etime))) + $oneday - 1: $today; // 结束日期
$stime           = !empty($stime) ? strtotime(date('Y-m-d',strtotime($stime)))  : ($etime - $show_days * $oneday + 1);  // 起始日期

/********************** 使用到的模型 ***********************/
$user                 = new User();  // 用户模型
$jjsan_user           = ct("user");  // 用户表对象
$jjsan_tradelog       = ct("tradelog"); // 订单对象
$user_data_cache      = ct("user_statistics_cache"); // 用户分析数据缓存对象
$jjsan_station_log    = ct("station_log"); // 充电站日志记录模型
$shopStation          = ct("shop_station"); // 商铺站点模型


// 租借成功订单状态(含借出状态的,归还状态)
$borrow_success_status = table_jjsan_tradelog::$borrow_success_status;
// 归还成功订单状态
$return_success_status = table_jjsan_tradelog::$return_success_status;

switch ($opt) {

    # 商户订单分析
    case 'order_analysis':
        $seconds_per_day  = 24*60*60;
        $default_show_day = 7;
        $default_date     = mktime(23, 59, 59, date("m"), date("d"), date("Y"));
        $exact_time       = '23:59:59';
        $endTime          = (strtotime($_GET['end_time']) > time() || empty($_GET['end_time'])) ? $default_date : strtotime($_GET['end_time'] . $exact_time);
        $beginTime        = strtotime($_GET['start_time']) ? strtotime($_GET['start_time']) :$endTime - $seconds_per_day * $default_show_day;

        // 业态
        $shopType = ct('shop_type')->get();
        foreach ($shopType as $v) {
            $newShopType[$v['id']] = $v['type'];
        }

        // 时间跨度限制
        if ($endTime - $beginTime > 31 * 24 * 60 * 60) {
            redirect('时间跨度最长一个月');
        }

        // 有商铺站点名称时, 说明是搜索单个商铺站点, 取shop_station_id进行统计单个商铺站点
        if ($title) {
            // 非全局搜索
            if (!$auth->globalSearch) {
                $all_shop_stations = $auth->checkShopStationIdIsAuthorized($shop_station_id) ? [$shop_station_id] : [];
            } else {
            // 全局搜索
                $all_shop_stations = [$shop_station_id];
            }
        }

        // 没有商铺站点名称时, 统计所有商铺站点
        if (!$title) {
            $accessShops = null;
            $accessCities = null;
            // 非全局搜索
            if (!$auth->globalSearch) {
                $accessCities = $auth->getAccessCities();
                $accessShops = $auth->getAccessShops();
            }
            $access_shop_stations = $shopStation->searchShopStation($_GET, 0, 0, $accessCities, $accessShops);
            $all_shop_stations = array_column($access_shop_stations['data'], 'id');
        }

        // 默认统计一周信息
        $time_list = array_reverse(range($beginTime, $endTime, $seconds_per_day));
        $date_list = array_map(function ($value) { return date('Y-m-d', $value); }, $time_list );

        $num = count($all_shop_stations);
        $start = ($page - 1) * $pageSize;


        // 读写分离需要初始化数据库
        // 讲临时表放到从服务器，读写都在从服务器操作
        $dbConfig = $_G['config']['db'];
        if (isset($dbConfig[1]['slave']) && !empty($dbConfig[1]['slave'])) {
            $newDbConfig = $dbConfig[1]['slave'];
            DB::init('db_driver_mysqli', $newDbConfig);
        }

        // 存在商铺站点ID时
        if ($all_shop_stations) {

            // 创建临时表
            $rand = random(3, 999);
            $borrow_rand_table = 'jjsan_tradelog_borrow_' . $admin->adminInfo['id'] . '_' . $rand;
            $return_rand_table = 'jjsan_tradelog_return_' . $admin->adminInfo['id'] . '_' . $rand;
            $tmp_borrow_table_sql = <<<EOF
CREATE TEMPORARY TABLE $borrow_rand_table (
    orderid varchar(32) NOT NULL,
    price decimal(8,2) NOT NULL DEFAULT '0.00',
    uid int(10) unsigned NOT NULL DEFAULT '0',
    status tinyint(1) NOT NULL DEFAULT '0', 
    borrow_shop_station_id mediumint(8) unsigned NOT NULL  DEFAULT '0',
    usefee decimal(8,2) unsigned NOT NULL DEFAULT '0.00',
    UNIQUE KEY `orderid` (`orderid`),
    KEY `status` (`status`),
    KEY `uid` (`uid`),
    KEY `borrow_shop_station_id` (`borrow_shop_station_id`)
) ENGINE=MyISAM;
EOF;

            $tmp_return_table_sql = <<<EOF
CREATE TEMPORARY TABLE $return_rand_table (
    orderid varchar(32) NOT NULL,
    price decimal(8,2) NOT NULL DEFAULT '0.00',
    uid int(10) unsigned NOT NULL DEFAULT '0',
    status tinyint(1) NOT NULL DEFAULT '0', 
    return_shop_station_id mediumint(8) unsigned NOT NULL  DEFAULT '0',
    usefee decimal(8,2) unsigned NOT NULL DEFAULT '0.00',
    borrow_shop_station_id mediumint(8) unsigned NOT NULL  DEFAULT '0',
    borrow_time int(10) unsigned NOT NULL DEFAULT '0',
    return_time int(10) unsigned NOT NULL DEFAULT '0',
    UNIQUE KEY `orderid` (`orderid`),
    KEY `status` (`status`),
    KEY `uid` (`uid`),
    KEY `return_shop_station_id` (`return_shop_station_id`),
    KEY `borrow_shop_station_id` (`borrow_shop_station_id`)
) ENGINE=MyISAM;
EOF;

            // 插入租借数据
            DB::query($tmp_borrow_table_sql);
            DB::query('INSERT INTO %i 
                SELECT `orderid`, `price`, `uid`, `status`, `borrow_shop_station_id`, `usefee` 
                FROM %t WHERE %i AND %i AND %i', [
                $borrow_rand_table,
                'jjsan_tradelog',
                DB::field('borrow_shop_station_id', $all_shop_stations),
                DB::field('borrow_time', $beginTime, '>='),
                DB::field('borrow_time', $endTime, '<=')
            ]);

            // 插入归还数据
            DB::query($tmp_return_table_sql);
            DB::query('INSERT INTO %i 
                SELECT `orderid`, `price`, `uid`, `status`, `return_shop_station_id`, `usefee`, `borrow_shop_station_id`, `borrow_time`, `return_time`
                FROM %t WHERE %i AND %i AND %i', [
                $return_rand_table,
                'jjsan_tradelog',
                DB::field('return_shop_station_id', $all_shop_stations),
                DB::field('borrow_time', $beginTime, '>='),
                DB::field('return_time', $endTime, '<=')
            ]);

            // 插入押金扣完未归还数据
            DB::query('INSERT INTO %i 
                SELECT `orderid`, `price`, `uid`, `status`, `return_shop_station_id`, `usefee`, `borrow_shop_station_id`, `borrow_time`, `return_time`
                FROM %t WHERE %i AND %i AND %i AND %i', [
                $return_rand_table,
                'jjsan_tradelog',
                DB::field('borrow_shop_station_id', $all_shop_stations),
                DB::field('status', [ORDER_STATUS_TIMEOUT_NOT_RETURN, ORDER_STATUS_LOSS]),
                DB::field('borrow_time', $beginTime, '>='),
                DB::field('return_time', $endTime, '<=')
            ]);

            $groupby_borrow_shop_station_id         = ' GROUP BY borrow_shop_station_id';
            $groupby_return_shop_station_id         = ' GROUP BY return_shop_station_id';
            $orderby_borrow_shop_station_id         = ' ORDER BY borrow_shop_station_id';
            $orderby_return_shop_station_id         = ' ORDER BY return_shop_station_id';
            $orderby_borrow_success_order           = ' ORDER BY borrow_success_order desc';
            // 默认显示借出成功的订单
            $orderby = $orderby ? : BORROW_SUCCESS_ORDER;
            // 默认不显示费用为0的订单
            $show_zero = $show_zero ? : 0;
            $limit = DB::limit($start, $pageSize);

            switch ($orderby) {

                # 租借成功的订单
                case BORROW_SUCCESS_ORDER:
                    $borrow_success_order_sql  = 'SELECT borrow_shop_station_id, count(*) as borrow_success_order ';
                    $borrow_success_order_sql .= 'FROM ' . $borrow_rand_table . ' ';
                    $borrow_success_order_sql .= 'WHERE %i AND %i ';
                    $borrow_success_order_sql .= 'GROUP BY borrow_shop_station_id ';
                    $borrow_success_order_sql .= 'ORDER BY borrow_success_order DESC ';
                    $borrow_success_order_sql .= '%i';

                    $borrow_success_order = DB::fetch_all($borrow_success_order_sql, [
                        DB::field('borrow_shop_station_id', $all_shop_stations),
                        DB::field('status', $borrow_success_status),
                        DB::limit($start, $pageSize)
                    ]);
                    $query_stations = array_column($borrow_success_order, 'borrow_shop_station_id');


                    $borrow_success_shop_stations_sql  = 'SELECT borrow_shop_station_id, count(*) as borrow_success_order ';
                    $borrow_success_shop_stations_sql .= 'FROM ' . $borrow_rand_table . ' ';
                    $borrow_success_shop_stations_sql .= 'WHERE %i AND %i ';
                    $borrow_success_shop_stations_sql .= 'GROUP BY borrow_shop_station_id';

                    $borrow_success_shop_stations = DB::fetch_all($borrow_success_shop_stations_sql, [
                        DB::field('borrow_shop_station_id', $all_shop_stations),
                        DB::field('status', $borrow_success_status)
                    ]);


                    if ($show_zero) {
                        // 加入借出订单为0的站点
                        $show_count = count($query_stations);
                        $borrow_success_shop_stations = array_column($borrow_success_shop_stations, 'borrow_shop_station_id');
                        $borrow_0_shop_stations = array_diff($all_shop_stations, $borrow_success_shop_stations);
                        if ($show_count < $pageSize) {
                            $filled_count = $pageSize - $show_count;
                            $offset = $show_count ? 0: $start - count($borrow_success_shop_stations);
                            $filled_sids = array_slice($borrow_0_shop_stations, $offset, $filled_count);
                            $query_stations = array_merge($query_stations, $filled_sids);
                        }
                    } else {
                        $num = count($borrow_success_shop_stations);;
                    }
                    break;

                # 归还成功的订单
                case RETURN_SUCCESS_ORDER:

                    $return_success_order_sql  = 'SELECT return_shop_station_id, count(*) as return_success_order ';
                    $return_success_order_sql .= 'FROM ' . $return_rand_table . ' ';
                    $return_success_order_sql .= 'WHERE %i AND %i ';
                    $return_success_order_sql .= 'GROUP BY return_shop_station_id ';
                    $return_success_order_sql .= 'ORDER BY return_success_order DESC ';
                    $return_success_order_sql .= '%i';

                    $return_success_order = DB::fetch_all($return_success_order_sql, [
                        DB::field('return_shop_station_id', $all_shop_stations),
                        DB::field('status', $return_success_status),
                        DB::limit($start, $pageSize)
                    ]);
                    $query_stations = array_column($return_success_order, 'return_shop_station_id');


                    $return_success_shop_stations_sql  = 'SELECT return_shop_station_id, count(*) as return_success_order ';
                    $return_success_shop_stations_sql .= 'FROM ' . $return_rand_table . ' ';
                    $return_success_shop_stations_sql .= 'WHERE %i AND %i ';
                    $return_success_shop_stations_sql .= 'GROUP BY return_shop_station_id';

                    $return_success_shop_stations = DB::fetch_all($return_success_shop_stations_sql, [
                        DB::field('return_shop_station_id', $all_shop_stations),
                        DB::field('status', $return_success_status)
                    ]);

                    if ($show_zero) {
                        // 加入归还订单为0的站点
                        $show_count = count($query_stations);
                        $return_success_shop_stations = array_column($return_success_shop_stations, 'return_shop_station_id');
                        $return_0_shop_stations = array_diff($all_shop_stations, $return_success_shop_stations);
                        if ($show_count < $pageSize) {
                            $filled_count = $pageSize - $show_count;
                            $offset = $show_count ? 0: $start - count($return_success_shop_stations);
                            $filled_sids = array_slice($return_0_shop_stations, $offset, $filled_count);
                            $query_stations = array_merge($query_stations, $filled_sids);
                        }
                    } else {
                        $num = count($return_success_shop_stations);
                    }
                    break;

                # 租借成功订单比
                case BORROW_SUCCESS_ORDER_RATE:
                    foreach ($all_shop_stations as $shop_station) {
                        $all_borrow_success_order = DB::result_first('SELECT count(*) FROM %i WHERE %i AND %i', [
                            $borrow_rand_table,
                            DB::field('borrow_shop_station_id', $shop_station),
                            DB::field('status', $borrow_success_status)
                        ]);
                        // 当选择"不显示借出订单为0"的条件，并且此站点借出订单为0的时候，不必进行统计
                        if (!$show_zero && !$all_borrow_success_order) {
                            continue;
                        }
                        $all_borrow_try_order = DB::result_first('SELECT count(*) FROM %i WHERE %i AND %i', [
                            $borrow_rand_table,
                            DB::field('borrow_shop_station_id', $shop_station),
                            DB::field("status", 0, ">")
                        ]);
                        $data[$shop_station] = $all_borrow_try_order ? round(($all_borrow_success_order / $all_borrow_try_order), 4) * 100 : 0;
                    }
                    arsort($data);
                    $num = count($data);
                    $all_shop_stations = array_keys($data);
                    $query_stations   = array_slice($all_shop_stations, $start, $pageSize);
                    break;

                #　归还成功订单比
                case RETURN_SUCCESS_ORDER_RATE:
                    foreach ($all_shop_stations as $shop_station) {
                        $all_return_success_order = DB::result_first('SELECT count(*) FROM %i WHERE %i AND %i', [
                            $return_rand_table,
                            DB::field('return_shop_station_id', $shop_station),
                            DB::field('status', $return_success_status)
                        ]);
                        // 当选择"不显示归还订单为0"的条件，并且此站点归还订单为0的时候，不必进行统计
                        if (!$show_zero && !$all_return_success_order) {
                            continue;
                        }
                        $all_return_try_order = DB::result_first('SELECT count(*) FROM %i WHERE %i', [
                            $return_rand_table,
                            DB::field('return_shop_station_id', $shop_station)
                        ]);
                        $data[$shop_station] = $all_return_try_order ? round(($all_return_success_order / $all_return_try_order), 4) * 100 : 0;
                    }
                    arsort($data);
                    $num = count($data);
                    $all_shop_stations = array_keys($data);
                    $query_stations   = array_slice($all_shop_stations, $start, $pageSize);
                    $total_query_stations   = array_slice($all_shop_stations, $start, $pageSize);
                    break;

                default:
                    # code...
                    break;
            }

            // 租借信息
            $all_data['borrow_success_order']   = DB::result_first('SELECT count(*) FROM %i WHERE %i AND %i', [
                $borrow_rand_table,
                DB::field('borrow_shop_station_id', $all_shop_stations),
                DB::field('status', $borrow_success_status)
            ]) + 0;
            $all_data['borrow_success_user']    = count(DB::fetch_all('SELECT count(*) FROM %i WHERE %i AND %i GROUP BY uid', [
                $borrow_rand_table,
                DB::field('borrow_shop_station_id', $all_shop_stations),
                DB::field('status', $borrow_success_status)
            ])) + 0;
            $all_data['borrow_try_user']        = count(DB::fetch_all('SELECT count(*) FROM %i WHERE %i AND %i GROUP BY uid', [
                $borrow_rand_table,
                DB::field('borrow_shop_station_id', $all_shop_stations),
                DB::field("status", 0, ">")
            ])) + 0;
            $all_data['borrow_try_order']       = DB::result_first('SELECT count(*) FROM %i WHERE %i AND %i', [
                $borrow_rand_table,
                DB::field('borrow_shop_station_id', $all_shop_stations),
                DB::field("status", 0, ">")
            ]) + 0;
            // 归还信息
            $all_data['return_success_order']   = DB::result_first('SELECT count(*) FROM %i WHERE %i AND %i', [
                $return_rand_table,
                DB::field('return_shop_station_id', $all_shop_stations),
                DB::field('status', $return_success_status)
            ]) + 0;
            $all_data['return_success_user']    = count(DB::fetch_all('SELECT count(*) FROM %i WHERE %i AND %i GROUP BY uid', [
                $return_rand_table,
                DB::field('return_shop_station_id', $all_shop_stations),
                DB::field('status', $return_success_status)
            ])) + 0;
            $all_data['total_usefee']           = DB::result_first('SELECT SUM(usefee) FROM %i WHERE %i', [
                $return_rand_table,
                DB::field('borrow_shop_station_id', $all_shop_stations),
            ]) + 0;
            // 超时订单(超时订单没有return_station)
            $all_data['timeout_order']          = DB::result_first('SELECT count(*) FROM %i WHERE %i', [
                $return_rand_table,
                DB::field('status', [ORDER_STATUS_TIMEOUT_NOT_RETURN, ORDER_STATUS_LOSS]),
            ]) + 0;

            $all_data['charge_order']           = DB::result_first('SELECT count(*) FROM %i WHERE %i AND %i', [
                $return_rand_table,
                DB::field('borrow_shop_station_id', $all_shop_stations),
                DB::field('usefee', 0, '>'),
            ]) + 0;
            // 借出时间总和
            $all_data['total_use_time']         = DB::result_first('SELECT sum(return_time) - sum(borrow_time) FROM %i WHERE %i', [
                $return_rand_table,
                DB::field('status', $return_success_status)
            ]) + 0;

            $all_data['seller_usefee']                  = sprintf("%.2f", $all_data['total_usefee'] - $all_data['timeout_order'] * 20);
            $all_data['usefee_per_order']               = $all_data['borrow_success_order'] ? round($all_data['total_usefee'] / $all_data['borrow_success_order'], 2) : 0;
            $all_data['charge_order_rate']              = $all_data['return_success_order'] ? round($all_data['charge_order'] / $all_data['return_success_order'], 4) * 100 . '%' : 0;
            $all_data['usefee_per_user']                = $all_data['borrow_success_user'] ? round($all_data['total_usefee'] / $all_data['borrow_success_user'], 2) : 0;
            $all_data['borrow_success_order_rate']      = $all_data['borrow_try_order'] ? round($all_data['borrow_success_order'] / $all_data['borrow_try_order'], 4) * 100 . '%' : 0;
            $all_data['return_success_order_rate']      = $all_data['borrow_try_order'] ? round($all_data['return_success_order'] / $all_data['borrow_try_order'], 4) * 100 . '%' : 0;
            $all_data['average_time']                   = $all_data['return_success_order'] ? round($all_data['total_use_time'] / $all_data['return_success_order']) : 0;
            // 客单价
            $all_data['usefee_per_return_order']        = $all_data['return_success_order'] ? round($all_data['total_usefee'] / $all_data['return_success_order'], 2) : 0;
            // 押金提现次数
            $refundCounts                               = ct('refund_log')->refund_count($beginTime, $endTime);
            // 押金提现率（老板定的）
            $all_data['refund_per_return_order']        = $all_data['return_success_order'] ? round($refundCounts / $all_data['return_success_order'], 4) * 100 . '%' : 0;
            // 归还率
            $all_data['return_order_per_borrow_order']  = $all_data['borrow_success_order'] ? round($all_data['return_success_order'] / $all_data['borrow_success_order'], 4) * 100 . '%' : 0;

            if ($title) {
                $curShopStation = ct('shop_station')->fetch($shop_station_id);
                $curShopId = ct('shop')->fetch($curShopStation['shopid']);
                $data[$shop_station_id]['station_title']        = $title;
                $data[$shop_station_id]['city']                 = ct('shop_station')->getCity($shop_station_id);
                $data[$shop_station_id]['station_id']           = $curShopStation['station_id'];
                $data[$shop_station_id]['station_shop_type']    = $newShopType[$curShopId['type']];
                $data[$shop_station_id]['borrow_success_order'] = $all_data['borrow_success_order'] + 0;
                $data[$shop_station_id]['return_success_order'] = $all_data['return_success_order'] + 0;
                $data[$shop_station_id]['borrow_try_user']      = $all_data['borrow_try_user'] + 0;
                $data[$shop_station_id]['borrow_success_user']  = $all_data['borrow_success_user'] + 0;
                $data[$shop_station_id]['return_success_user']  = $all_data['return_success_user'] + 0;
                $data[$shop_station_id]['total_usefee']         = $all_data['total_usefee'] + 0;
                $data[$shop_station_id]['seller_usefee']        = $all_data['seller_usefee'] + 0;
                $data[$shop_station_id]['charge_order']         = $all_data['charge_order'] + 0;
                $data[$shop_station_id]['return_all_order']     = $all_data['return_success_order'] + 0;
                $data[$shop_station_id]['usefee_per_order']     = $all_data['usefee_per_order'] + 0;
                $data[$shop_station_id]['charge_order_rate']    = $all_data['charge_order_rate'];
                $data[$shop_station_id]['borrow_try_order']     = $all_data['borrow_try_order'] + 0;
                $data[$shop_station_id]['return_try_order']     = 0;
                $data[$shop_station_id]['borrow_success_order_rate']   = $all_data['borrow_success_order_rate'];
                $data[$shop_station_id]['return_success_order_rate']   = $all_data['return_success_order_rate'];
                $data[$shop_station_id]['usefee_per_user']      = $all_data['usefee_per_user'] + 0;
                $data[$shop_station_id]['total_use_time']       = $all_data['total_use_time'] + 0;
                $data[$shop_station_id]['average_time']         = $all_data['average_time'] + 0;
                // 客单价
                $data[$shop_station_id]['usefee_per_return_order']     = $all_data['usefee_per_return_order'] + 0;
                // 押金提现率（站点没有押金提现率）
                $data[$shop_station_id]['refund_per_return_order']        = 0;
                // 归还率
                $data[$shop_station_id]['return_order_per_borrow_order']= $all_data['return_order_per_borrow_order'] + 0;
            } else {

                $borrow_success_order = $borrow_success_order ? : DB::fetch_all('SELECT borrow_shop_station_id,count(*) as borrow_success_order FROM %i WHERE %i AND %i' . $groupby_borrow_shop_station_id . $orderby_borrow_success_order, [
                    $borrow_rand_table,
                    DB::field('borrow_shop_station_id', $query_stations),
                    DB::field('status', $borrow_success_status)
                ]);
                $return_success_order = $return_success_order ? : DB::fetch_all('SELECT return_shop_station_id,count(*) as return_success_order FROM %i WHERE %i AND %i' . $groupby_return_shop_station_id . $orderby_return_shop_station_id, [
                    $return_rand_table,
                    DB::field('return_shop_station_id', $query_stations),
                    DB::field('status', $return_success_status)
                ]);
                $borrow_try_user = DB::fetch_all('SELECT uid,count(*),borrow_shop_station_id FROM %i WHERE %i AND %i GROUP BY uid, borrow_shop_station_id', [
                    $borrow_rand_table,
                    DB::field("borrow_shop_station_id", $query_stations),
                    DB::field("status", 0, ">")
                ]);
                $borrow_success_user = DB::fetch_all('SELECT uid,count(*),borrow_shop_station_id FROM %i WHERE %i AND %i GROUP BY uid, borrow_shop_station_id', [
                    $borrow_rand_table,
                    DB::field('borrow_shop_station_id', $query_stations),
                    DB::field('status', $borrow_success_status)
                ]);
                $return_success_user = DB::fetch_all('SELECT uid,count(*),return_shop_station_id FROM %i WHERE %i AND %i GROUP BY uid, return_shop_station_id', [
                    $return_rand_table,
                    DB::field('return_shop_station_id', $query_stations),
                    DB::field('status', $return_success_status)
                ]);
                $total_usefee = DB::fetch_all('SELECT borrow_shop_station_id,SUM(usefee) as total_usefee FROM %i WHERE %i AND %i' . $groupby_borrow_shop_station_id, [
                    $return_rand_table,
                    DB::field('borrow_shop_station_id', $query_stations),
                    DB::field('status', 0, '>')
                ]);
                // 押金扣完未归还订单没有return_shop_station_id，只能用borrow_shop_station_id
                $timeout_order = DB::fetch_all('SELECT borrow_shop_station_id,count(*) as timeout_order FROM %i WHERE %i' . $groupby_borrow_shop_station_id . $orderby_borrow_shop_station_id, [
                    $return_rand_table,
                    DB::field('status', [ORDER_STATUS_TIMEOUT_NOT_RETURN, ORDER_STATUS_LOSS]),
                ]);
                $charge_order = DB::fetch_all('SELECT borrow_shop_station_id,count(*) as charge_order FROM %i WHERE %i AND %i AND %i' . $groupby_borrow_shop_station_id . $orderby_borrow_shop_station_id, [
                    $return_rand_table,
                    DB::field('borrow_shop_station_id', $query_stations),
                    DB::field('status', 0, '>'),
                    DB::field('usefee', 0, '>'),
                ]);
                $return_all_order = DB::fetch_all('SELECT return_shop_station_id,count(*) as return_all_order FROM %i WHERE %i AND %i' . $groupby_return_shop_station_id . $orderby_return_shop_station_id, [
                    $return_rand_table,
                    DB::field('return_shop_station_id', $query_stations),
                    DB::field('status', $return_success_status),
                ]);
                $borrow_try_order = DB::fetch_all('SELECT borrow_shop_station_id, count(*) as borrow_try_order FROM %i WHERE %i' . $groupby_borrow_shop_station_id, [
                    $borrow_rand_table,
                    DB::field('borrow_shop_station_id',$query_stations),
                ]);
                $return_try_order = DB::fetch_all('SELECT return_shop_station_id, count(*) as return_try_order FROM %i WHERE %i' . $groupby_return_shop_station_id, [
                    $return_rand_table,
                    DB::field('return_shop_station_id',$query_stations),
                ]);
                $total_use_time = DB::fetch_all('SELECT return_shop_station_id, sum(return_time) - sum(borrow_time) as total_use_time FROM %i WHERE %i' . $groupby_return_shop_station_id, [
                    $return_rand_table,
                    DB::field('return_shop_station_id',$query_stations),
                ]);

                $borrow_success_order = array_column($borrow_success_order, 'borrow_success_order', 'borrow_shop_station_id');
                $return_success_order = array_column($return_success_order, 'return_success_order', 'return_shop_station_id');
                $total_usefee = array_column($total_usefee, 'total_usefee', 'borrow_shop_station_id');
                $timeout_order= array_column($timeout_order, 'timeout_order', 'borrow_shop_station_id');
                $charge_order = array_column($charge_order, 'charge_order', 'borrow_shop_station_id');
                $return_all_order = array_column($return_all_order, 'return_all_order', 'return_shop_station_id');
                $borrow_try_order = array_column($borrow_try_order, 'borrow_try_order', 'borrow_shop_station_id');
                $return_try_order = array_column($return_try_order, 'return_try_order', 'return_shop_station_id');
                $total_use_time = array_column($total_use_time, 'total_use_time', 'return_shop_station_id');
                $data = array();
                foreach ($query_stations as $value) {
                    $shopStationInfo = ct('shop_station')->fetch($value);
                    $shopInfo = ct('shop')->fetch($shopStationInfo['shopid']);
                    $data[$value]['station_title']        = $shopStationInfo['title'];
                    $data[$value]['station_id']           = $shopStationInfo['station_id'];
                    $data[$value]['station_shop_type']    = $newShopType[$shopInfo['type']];
                    $data[$value]['average_time']         = $return_success_order[$value] ? round($total_use_time[$value] / $return_success_order[$value]) : 0;
                    $data[$value]['city']                 = ct('shop_station')->getCity($value);
                    $data[$value]['borrow_success_order'] = $borrow_success_order[$value] ? : 0;
                    $data[$value]['return_success_order'] = $return_success_order[$value] ? : 0;
                    $data[$value]['borrow_try_user']      = 0;
                    $data[$value]['borrow_success_user']  = 0;
                    $data[$value]['return_success_user']  = 0;
                    $data[$value]['total_usefee']         = $total_usefee[$value] ? : 0;
                    $data[$value]['seller_usefee']        = sprintf("%.2f", $data[$value]['total_usefee'] - $timeout_order[$value] * 20);
                    $data[$value]['charge_order']         = $charge_order[$value] ? : 0;
                    $data[$value]['return_all_order']     = $return_all_order[$value] ? : 0;
                    $data[$value]['usefee_per_order']     = $data[$value]['borrow_success_order'] ? round($data[$value]['total_usefee'] / $data[$value]['borrow_success_order'], 2) : 0;
                    $data[$value]['charge_order_rate']    = $data[$value]['return_all_order'] ? round($charge_order[$value] / $data[$value]['return_all_order'], 4) * 100 . '%' : 0;
                    $data[$value]['borrow_try_order']     = $borrow_try_order[$value] ? : 0;
                    $data[$value]['return_try_order']     = $return_try_order[$value] ? : 0;
                    $data[$value]['borrow_success_order_rate']   = $data[$value]['borrow_try_order'] ? round($data[$value]['borrow_success_order'] / $data[$value]['borrow_try_order'], 4) * 100 . '%' : 0;
                    $data[$value]['return_success_order_rate']   = $data[$value]['return_try_order'] ? round($data[$value]['return_success_order'] / $data[$value]['return_try_order'], 4) * 100 . '%' : 0;
                    // 客单价
                    $data[$value]['usefee_per_return_order']     = $data[$value]['return_success_order'] ? round($data[$value]['total_usefee'] / $data[$value]['return_success_order'], 2) : 0;
                    // 押金提现率（站点没有押金提现率）
                    $data[$value]['refund_per_return_order']     = 0;
                    // 归还率
                    $data[$value]['return_order_per_borrow_order']= $data[$value]['borrow_success_order'] > 0 ? round($data[$value]['return_success_order'] / $data[$value]['borrow_success_order'], 4) * 100 . '%' : 0;

                }

                foreach ($borrow_try_user as $key => $value) {
                    $data[$value['borrow_shop_station_id']]['borrow_try_user'] += 1;
                }
                foreach ($borrow_success_user as $key => $value) {
                    $data[$value['borrow_shop_station_id']]['borrow_success_user'] += 1;
                }
                foreach ($return_success_user as $key => $value) {
                    $data[$value['return_shop_station_id']]['return_success_user'] += 1;
                }
                foreach ($query_stations as $value) {
                    $data[$value]['usefee_per_user'] = $data[$value]['borrow_success_user'] ? round($data[$value]['total_usefee'] / $data[$value]['borrow_success_user'], 2) : 0;
                }
            }
            $record_count = $num;
            $first_date = date("Y-m-d", $beginTime + 1);
            $last_date = date("Y-m-d", $endTime - 1);
            unset($_GET['page']);
            $pagehtm = getPages($num, $page - 1, $pageSize, '/index.php?'.http_build_query($_GET));

            if ($_GET['export']) {
                $borrow_success_order = DB::fetch_all('SELECT borrow_shop_station_id,count(*) as borrow_success_order FROM %i WHERE %i AND %i' . $groupby_borrow_shop_station_id . $orderby_borrow_shop_station_id, [
                    $borrow_rand_table,
                    DB::field('borrow_shop_station_id', $all_shop_stations),
                    DB::field('status', $borrow_success_status)
                ]);
                $return_success_order = DB::fetch_all('SELECT return_shop_station_id,count(*) as return_success_order FROM %i WHERE %i AND %i' . $groupby_return_shop_station_id . $orderby_return_shop_station_id, [
                    $return_rand_table,
                    DB::field('return_shop_station_id', $all_shop_stations),
                    DB::field('status', $return_success_status)
                ]);
                $borrow_try_user = DB::fetch_all('SELECT uid,count(*),borrow_shop_station_id FROM %i WHERE %i AND %i GROUP BY uid, borrow_shop_station_id', [
                    $borrow_rand_table,
                    DB::field("borrow_shop_station_id", $all_shop_stations),
                    DB::field("status", 0, ">")
                ]);
                $borrow_success_user = DB::fetch_all('SELECT uid,count(*),borrow_shop_station_id FROM %i WHERE %i AND %i GROUP BY uid, borrow_shop_station_id', [
                    $borrow_rand_table,
                    DB::field('borrow_shop_station_id', $all_shop_stations),
                    DB::field('status', $borrow_success_status)
                ]);
                $return_success_user = DB::fetch_all('SELECT uid,count(*),return_shop_station_id FROM %i WHERE %i AND %i GROUP BY uid, return_shop_station_id', [
                    $return_rand_table,
                    DB::field('return_shop_station_id', $all_shop_stations),
                    DB::field('status', $return_success_status)
                ]);
                $total_usefee = DB::fetch_all('SELECT borrow_shop_station_id,IFNULL(SUM(usefee),0) as total_usefee FROM %i WHERE %i AND %i' . $groupby_borrow_shop_station_id, [
                    $return_rand_table,
                    DB::field('borrow_shop_station_id', $all_shop_stations),
                    DB::field('status', 0, '>')
                ]);
                $timeout_order = DB::fetch_all('SELECT borrow_shop_station_id,count(*) as timeout_order FROM %i WHERE %i AND %i' . $groupby_borrow_shop_station_id . $orderby_borrow_shop_station_id, [
                    $return_rand_table,
                    DB::field('borrow_shop_station_id', $all_shop_stations),
                    DB::field('status', [ORDER_STATUS_TIMEOUT_NOT_RETURN, ORDER_STATUS_LOSS]),
                ]);
                $charge_order = DB::fetch_all('SELECT borrow_shop_station_id,count(*) as charge_order FROM %i WHERE %i AND %i AND %i' . $groupby_borrow_shop_station_id . $orderby_borrow_shop_station_id, [
                    $return_rand_table,
                    DB::field('borrow_shop_station_id', $all_shop_stations),
                    DB::field('status', 0, '>'),
                    DB::field('usefee', 0, '>'),
                ]);
                $return_all_order = DB::fetch_all('SELECT return_shop_station_id,count(*) as return_all_order FROM %i WHERE %i AND (%i OR (%i AND %i))' . $groupby_return_shop_station_id . $orderby_return_shop_station_id, [
                    $return_rand_table,
                    DB::field('return_shop_station_id', $all_shop_stations),
                    DB::field('usefee', 0, '>'),
                    DB::field('status', $return_success_status),
                    DB::field('usefee', 0),
                ]);
                $borrow_try_order = DB::fetch_all('SELECT borrow_shop_station_id, count(*) as borrow_try_order FROM %i WHERE %i' . $groupby_borrow_shop_station_id, [
                    $borrow_rand_table,
                    DB::field('borrow_shop_station_id',$all_shop_stations),
                ]);
                $total_use_time = DB::fetch_all('SELECT return_shop_station_id, sum(return_time) - sum(borrow_time) as total_use_time FROM %i WHERE %i' . $groupby_return_shop_station_id, [
                    $return_rand_table,
                    DB::field('return_shop_station_id', $all_shop_stations),
                ]);
                $borrow_success_order = array_column($borrow_success_order, 'borrow_success_order', 'borrow_shop_station_id');
                $return_success_order = array_column($return_success_order, 'return_success_order', 'return_shop_station_id');
                $total_usefee = array_column($total_usefee, 'total_usefee', 'borrow_shop_station_id');
                $timeout_order= array_column($timeout_order, 'timeout_order', 'borrow_shop_station_id');
                $charge_order = array_column($charge_order, 'charge_order', 'borrow_shop_station_id');
                $return_all_order = array_column($return_all_order, 'return_all_order', 'return_shop_station_id');
                $borrow_try_order = array_column($borrow_try_order, 'borrow_try_order', 'borrow_shop_station_id');
                $total_use_time = array_column($total_use_time, 'total_use_time', 'return_shop_station_id');
                foreach ($all_shop_stations as $key => $value) {
                    $shopStationInfo = ct('shop_station')->fetch($value);
                    $shopInfo = ct('shop')->fetch($shopStationInfo['shopid']);
                    $data[$value]['shop_station_id']      = $value;
                    $data[$value]['station_id']           = $shopStationInfo['station_id'];
                    $data[$value]['station_title']        = $shopStationInfo['title'];
                    $data[$value]['station_shop_type']    = $newShopType[$shopInfo['type']];
                    $data[$value]['city']                 = $shopInfo['province'] . $shopInfo['city'];
                    $data[$value]['borrow_success_order'] = $borrow_success_order[$value] ? : 0;
                    $data[$value]['return_success_order'] = $return_success_order[$value] ? : 0;
                    $data[$value]['borrow_try_user']      = 0;
                    $data[$value]['borrow_success_user']  = 0;
                    $data[$value]['return_success_user']  = 0;
                    $data[$value]['total_usefee']         = $total_usefee[$value] ? : 0;
                    $data[$value]['seller_usefee']        = sprintf("%.2f", $data[$value]['total_usefee'] - $timeout_order[$value] * 20);
                    $data[$value]['charge_order']         = $charge_order[$value] ? : 0;
                    $data[$value]['return_all_order']     = $return_all_order[$value] ? : 0;
                    $data[$value]['usefee_per_order']     = $data[$value]['borrow_success_order'] ? round($data[$value]['total_usefee'] / $data[$value]['borrow_success_order'], 2) : 0;
                    $data[$value]['charge_order_rate']    = $data[$value]['return_all_order'] ? round($charge_order[$value] / $data[$value]['return_all_order'], 4) * 100 . '%' : 0;
                    $data[$value]['borrow_try_order']     = $borrow_try_order[$value] ? : 0;
                    $data[$value]['success_order_rate']   = $data[$value]['borrow_try_order'] ? round($data[$value]['borrow_success_order'] / $data[$value]['borrow_try_order'], 4) * 100 . '%' : 0;
                    $data[$value]['average_time']         = $return_success_order[$value] ? round($total_use_time[$value] / $return_success_order[$value]) : 0;

                    // 客单价
                    $data[$value]['usefee_per_return_order']     = $data[$value]['return_success_order'] ? round($data[$value]['total_usefee'] / $data[$value]['return_success_order'], 2) : 0;
                    // 押金提现率（站点没有押金提现率）
                    $data[$value]['refund_per_return_order']     = 0;
                    // 归还率
                    $data[$value]['return_order_per_borrow_order']= $data[$value]['borrow_success_order'] > 0 ? round($data[$value]['return_success_order'] / $data[$value]['borrow_success_order'], 4) * 100 . '%' : 0;

                }
                foreach ($borrow_try_user as $key => $value) {
                    $data[$value['borrow_shop_station_id']]['borrow_try_user'] += 1;
                }
                foreach ($borrow_success_user as $key => $value) {
                    $data[$value['borrow_shop_station_id']]['borrow_success_user'] += 1;
                }
                foreach ($return_success_user as $key => $value) {
                    $data[$value['return_shop_station_id']]['return_success_user'] += 1;
                }
                foreach ($all_shop_stations as $value) {
                    $data[$value]['usefee_per_user'] = $data[$value]['borrow_success_user'] ? round($data[$value]['total_usefee'] / $data[$value]['borrow_success_user'], 2) : 0;
                }

                $sheetarray[] = create_excel_column($data, 'station_title', $first_date .'---'. $last_date, '总计');
                $sheetarray[] = create_excel_column($data, 'shop_station_id', '商铺站点id', '总计');
                $sheetarray[] = create_excel_column($data, 'station_id', '站点id', '总计');
                $sheetarray[] = create_excel_column($data, 'station_shop_type', '业态', '总计');

                $sheetarray[] = create_excel_column($data, 'city', lang('plugin/jjsan', 'city'), $city ? : lang('plugin/jjsan', 'all'));
                $sheetarray[] = create_excel_column($data, 'borrow_success_order', '租借成功订单数', $all_data['borrow_success_order']);
                $sheetarray[] = create_excel_column($data, 'return_success_order', '归还成功订单数', $all_data['return_success_order']);
                $sheetarray[] = create_excel_column($data, 'borrow_try_user', '尝试租借用户数', $all_data['borrow_try_user']);
                $sheetarray[] = create_excel_column($data, 'borrow_success_user', '借成功用户数', $all_data['borrow_success_user']);
                $sheetarray[] = create_excel_column($data, 'return_success_user', '还成功用户数', $all_data['return_success_user']);
                $sheetarray[] = create_excel_column($data, 'total_usefee', '盈利总额（元）', $all_data['total_usefee']);
                $sheetarray[] = create_excel_column($data, 'seller_usefee', '盈利总额（扣除雨伞成本20元）', $all_data['seller_usefee']);
                $sheetarray[] = create_excel_column($data, 'charge_order', '盈利订单数', $all_data['charge_order']);
                $sheetarray[] = create_excel_column($data, 'usefee_per_user', '平均每人收益', $all_data['usefee_per_user']);
                $sheetarray[] = create_excel_column($data, 'usefee_per_order', '平均每人次收益', $all_data['usefee_per_order']);
                $sheetarray[] = create_excel_column($data, 'charge_order_rate', '租金转化率', $all_data['charge_order_rate']);
                $sheetarray[] = create_excel_column($data, 'borrow_try_order', '总租借订单数（所有状态的订单，包括成功和不成功的）', $all_data['borrow_try_order']);
                $sheetarray[] = create_excel_column($data, 'success_order_rate', '租借成功订单比（租借成功订单数/总订单数）', $all_data['success_order_rate']);
                $sheetarray[] = create_excel_column($data, 'average_time', '平均租借时间（秒）', $all_data['average_time']);

                $sheetarray[] = create_excel_column($data, 'usefee_per_return_order', '客单价（元）', $all_data['usefee_per_return_order']);
                $sheetarray[] = create_excel_column($data, 'refund_per_return_order', '押金提现率', $all_data['refund_per_return_order']);
                $sheetarray[] = create_excel_column($data, 'return_order_per_borrow_order', '归还率', $all_data['return_order_per_borrow_order']);

                $sheetarray = transpose($sheetarray);

                export_excel($sheetarray, 'ShopOrderData_' . date("Ymd", $beginTime + 1) . '_' . date("Ymd", $endTime - 1));
                exit;

            }
        } else {
            redirect('商铺站点不存在');
        }

        break;

    // 新用户数据
    case "new_user_list":
        $days = [];
        $data = [];

        $sum['date'] = '总计';
        $sum['origin'] = $platform_arr[$platform];

        for($a = 0;$stime < $etime ;$etime -= $oneday,$a ++){
            $date = date('Y-m-d',$etime);
            $data[$a]['date'] 				= date('Y-m-d',(strtotime($date)));
            $data[$a]['origin']							= $platform_arr[$platform]; 			// 来源

            $user_cache = $user_data_cache -> get($date,$platform);
            if($date != date('Y-m-d',$today)){
                $data[$a]['subscribe_user_count'] = $user_cache['subscribe_user_count_new'];
                $data[$a]['unsubscribe_user_count'] = $user_cache['unsubscribe_user_count_new'];
                $data[$a]['pay_button_user_count'] = $user_cache['pay_button_user_count_new'];
                $data[$a]['shop_page_user_count'] = $user_cache['shop_page_user_count_new'];
                $data[$a]['user_scan_user_count'] = $user_cache['user_scan_user_count_new'];
                $data[$a]['top_up_user_count'] = $user_cache['top_up_user_count_new'];
                $data[$a]['increse_order_user'] = $user_cache['increse_order_user_new'];
                $data[$a]['success_user_count'] = $user_cache['success_user_count_new'];
                $data[$a]['success_user_order_count'] = $user_cache['success_user_order_count_new'];
            }else{
                // 统计事件人数
                $temp_user = $jjsan_user ->  user_log_user_count($date,$platform,'new');
                foreach($temp_user as $t){ $data[$a][$t['type']] = $t['num'];}
                $data[$a]['subscribe_user_count'] 	= $data[$a][$jjsan_user::EVENT_SUBSCRIBE] ?:0;
                $data[$a]['unsubscribe_user_count'] = $data[$a][$jjsan_user::EVENT_UNSUBSCRIBE] ?:0;
                $data[$a]['pay_button_user_count'] 	= $data[$a][$jjsan_user::EVENT_SHOP_PAY] ?:0;
                $data[$a]['shop_page_user_count'] 	= $data[$a][$jjsan_user::EVENT_SHOP_PAGE] ?:0;
                $data[$a]['user_scan_user_count'] 	= $data[$a][$jjsan_user::EVENT_SCAN]?:0;

                $data[$a]['success_user_order_count'] = $jjsan_user -> success_user_order_count($date,$platform,'new'); // 新关注用户租借成功订单数
                $data[$a]['top_up_user_count'] 		= $jjsan_user -> top_up_user_count($date,$platform,'new'); // 付押金的新用户数
                $data[$a]['increse_order_user'] 	= $jjsan_user -> increse_order_user($date,$platform); // 新用户增长数
                $data[$a]['success_user_count']		= $jjsan_user -> success_order_user_count($date,$platform,'new'); // 租借成功用户数
            }
            $data[$a]['rate']				= ($data[$a]['subscribe_user_count'] ? number_format($data[$a]['success_user_count'] / $data[$a]['subscribe_user_count'] * 100,2,'.','') : 0) . '%';
            $data[$a]['operate_rate']		= ($data[$a]['shop_page_user_count'] ? number_format($data[$a]['success_user_count'] / $data[$a]['shop_page_user_count'] * 100,2,'.','') : 0) . '%';
            $data[$a]['user_success_order_rate']   = ($data[$a]['subscribe_user_count'] ? number_format($data[$a]['success_user_order_count'] / $data[$a]['subscribe_user_count'] * 100,2,'.','') : 0) . '%';
        }

        foreach ($data as $d) {
            $sum['subscribe_user_count'] += $d['subscribe_user_count'];
            $sum['unsubscribe_user_count'] += $d['unsubscribe_user_count'];
            $sum['pay_button_user_count'] += $d['pay_button_user_count'];
            $sum['shop_page_user_count'] += $d['shop_page_user_count'];
            $sum['user_scan_user_count'] += $d['user_scan_user_count'];
            $sum['top_up_user_count'] += $d['top_up_user_count'];
            $sum['increse_order_user'] += $d['increse_order_user'];
            $sum['success_user_count'] += $d['success_user_count'];
            $sum['success_user_order_count'] += $d['success_user_order_count'];
        }

        $sum['rate']				= ($sum['subscribe_user_count'] ? number_format($sum['success_user_count'] / $sum['subscribe_user_count'] * 100,2,'.','') : 0) . '%';
        $sum['operate_rate']		= ($sum['shop_page_user_count'] ? number_format($sum['success_user_count'] / $sum['shop_page_user_count'] * 100,2,'.','') : 0) . '%';
        $sum['user_success_order_rate']   = ($sum['subscribe_user_count'] ? number_format($sum['success_user_order_count'] / $sum['subscribe_user_count'] * 100,2,'.','') : 0) . '%';

        $count = count($data);

        // 分页显示
        for($x = ($page - 1) * 10,$t = 0;($t < 10 && $x <= $a);$x++,$t++){
            $show_data[$x] = $data[$x];
        }
        unset($_GET['page']);
        $pagehtm = getPages($count, $page - 1,10,"index.php?".http_build_query($_GET));
        if ($_GET['export']) {
            // print_r($data);
            $sheetarray[] = create_excel_column($data, 'date', '日期', $sum['date']);
            $sheetarray[] = create_excel_column($data, 'origin', '来源', $sum['origin']);
            $sheetarray[] = create_excel_column($data, 'subscribe_user_count', '新关注', $sum['subscribe_user_count']);
            $sheetarray[] = create_excel_column($data, 'increse_order_user', '新用户增长数', $sum['increse_order_user']);
            $sheetarray[] = create_excel_column($data, 'shop_page_user_count', '借操作新用户数', $sum['shop_page_user_count']);
            $sheetarray[] = create_excel_column($data, 'top_up_user_count', '付押金成功新用户数', $sum['top_up_user_count']);
            $sheetarray[] = create_excel_column($data, 'success_user_count', '租借成功新用户数', $sum['success_user_count']);
            $sheetarray[] = create_excel_column($data, 'rate', '新关注用户转化率', $sum['rate']);
            $sheetarray[] = create_excel_column($data, 'operate_rate', '操作用户转化率', $sum['operate_rate']);

            $sheetarray = transpose($sheetarray);

            export_excel($sheetarray, 'NewUserData_' .  end($data)['date'] . '_' . $data[0]['date']);
            exit;
        }
        break;

    // 老用户数据
    case "old_user_list":
        $days = [];
        $data = [];
        for($a = 0;$stime < $etime ;$etime -= $oneday,$a ++){
            $date = date('Y-m-d',$etime);
            $data[$a]['date'] 				= $date;
            $data[$a]['origin']							= $platform_arr[$platform];

            $user_cache     = $user_data_cache     -> get($date,$platform);// 当天
            $user_cache_pre = $user_data_cache     -> get(date('Y-m-d',strtotime($date) - $oneday),$platform); // 昨天

            if($date != date('Y-m-d',$today)){
                $data[$a]['user_accumulated'] 		= $user_cache_pre['user_accumulated_old']; // 当天用户老数据 就是前一天用户总数据
                $data[$a]['success_user_count']		= $user_cache['success_user_count_old'];
                $data[$a]['success_fee_user_count'] = $user_cache['success_fee_user_count_old'];
            }else{
                $data[$a]['user_accumulated'] 				= $user_cache_pre['user_accumulated_old']; // 当天用户老数据 就是前一天用户总数据
                $data[$a]['success_user_count']				= $jjsan_user -> success_order_user_count($date,$platform,'old'); // 租借成功用户数
                $data[$a]['success_fee_user_count']			= $jjsan_user -> success_order_user_count($date,$platform,'old',true); // 租借成功用户数 付费
            }
            $data[$a]['user_active_rate']					= (number_format($data[$a]['success_user_count'] / $data[$a]['user_accumulated'] * 100 , 2 , '.' , '')) . '%';
        }
        $count = count($data);
        // 算累加总计数据
        $sum['date'] =  "总计";
        $sum['origin'] = $platform_arr[$platform];
        $sum['user_accumulated'] = $data[0]['user_accumulated'];
        foreach ($data as $d) {
            $sum['success_user_count'] += $d['success_user_count'];
            $sum['success_fee_user_count'] += $d['success_fee_user_count'];
        }
        $sum['user_active_rate'] = (number_format($sum['success_user_count'] / ($sum['user_accumulated'] * $count) * 100 , 2 , '.' , '')) . '%';
        // 分页显示
        for($x = ($page - 1) * 10,$t = 0;($t < 10 && $x <= $a);$x++,$t++){
            $show_data[$x] = $data[$x];
        }
        unset($_GET['page']);
        $pagehtm = getPages($count, $page - 1,10,"index.php?".http_build_query($_GET));
        if ($_GET['export']) {
            // print_r($data);
            $sheetarray[] = create_excel_column($data, 'date', '日期', $sum['date']);
            $sheetarray[] = create_excel_column($data, 'origin', '来源', $sum['origin']);
            $sheetarray[] = create_excel_column($data, 'user_accumulated', '老用户总数', $sum['user_accumulated']);
            $sheetarray[] = create_excel_column($data, 'success_user_count', '租借成功老用户人数', $sum['success_user_count']);
            $sheetarray[] = create_excel_column($data, 'success_fee_user_count', '付费老用户人数', $sum['success_fee_user_count']);
            $sheetarray[] = create_excel_column($data, 'user_active_rate', '每日老用户活跃度', $sum['user_active_rate']);

            $sheetarray = transpose($sheetarray);

            export_excel($sheetarray, 'OldUserData_' .  end($data)['date'] . '_' . $data[0]['date']);
            exit;
        }
        break;

    // 总用户数据
    case "user_data_count":
            $days = [];
            $data = [];

            // 某段时间　借操作人数
            $sum['shop_page_user_count'] = $jjsan_user -> shop_page_user_count_by_date($stime,$etime,$platform);
            // 某段时间　付押金人数
            $sum['top_up_user_count']    = $jjsan_user -> top_up_user_count_by_date($stime,$etime,$platform);
            // 某段时间　租借成功人数
            $sum['success_user_count']     = $jjsan_user -> success_user_count_by_date($stime,$etime,$platform);
            $sum['success_fee_user_count'] = $jjsan_user -> success_user_count_by_date($stime,$etime,$platform,true);

            for($a = 0; $stime <= $etime ;$etime -= $oneday,$a ++){
                $date = date('Y-m-d',$etime);
                $data[$a]['date'] 							= $date;
                $data[$a]['origin']							= $platform_arr[$platform]; // 来源
                $user_cache = $user_data_cache -> get($date,$platform);
                if($date != date('Y-m-d',$today)){
                    foreach ($user_cache as $key => $value) {
                        $data[$a][$key] = $value;
                    }
                }else{
                    $temp_user = $jjsan_user ->  user_log_user_count($date,$platform); // 这些事件的人数
                    foreach($temp_user as $t){
                        $data[$a][$t['type']] = $t['num'];
                    }
                    $data[$a]['subscribe_user_count']   = $data[$a][$jjsan_user::EVENT_SUBSCRIBE] ?:0;
                    $data[$a]['unsubscribe_user_count'] = $data[$a][$jjsan_user::EVENT_UNSUBSCRIBE] ?:0;
                    $data[$a]['pay_button_user_count']  = $data[$a][$jjsan_user::EVENT_SHOP_PAY] ?:0;
                    $data[$a]['shop_page_user_count']   = $data[$a][$jjsan_user::EVENT_SHOP_PAGE] ?:0;
                    $data[$a]['user_scan_user_count']   = $data[$a][$jjsan_user::EVENT_SCAN]?:0;
                    $data[$a]['top_up_success_count'] 	= $jjsan_user -> top_up_success_count($date,$platform); // 付押金次数　大于100　才算押金
                    $data[$a]['top_up_user_count'] 		= $jjsan_user -> top_up_user_count($date,$platform); // 付押金人数　大于100　才算押金

                    $data[$a]['refund_count'] 					= $jjsan_user -> refund_count($date,$platform); // 退款次数
                    $data[$a]['success_user_count']				= $jjsan_user -> success_order_user_count($date,$platform); // 租借成功用户数
                    $data[$a]['order_count']					= $jjsan_user -> order_count($date,$platform); // 租借成功次数
                    $data[$a]['success_fee_user_count']			= $jjsan_user -> success_order_user_count($date,$platform,[],true); // 付费订单用户数
                    $data[$a]['success_fee_user_accumulated']	= $jjsan_user -> success_fee_order_user_accumulated($date,$platform); // 付费订单累积用户数
                }

                $data[$a]['user_increase_count']			= $data[$a]['subscribe_user_count'] - $data[$a]['unsubscribe_user_count']; // 净增
                $data[$a]['user_operate_success_success']	= ($data[$a]['shop_page_user_count'] ? number_format($data[$a]['success_user_count'] / $data[$a]['shop_page_user_count'] * 100, 2, '.', '') : 0) . "%"; // 操作用户转化率
                $data[$a]['refund_up_top_rate']	            = ($data[$a]['top_up_success_count'] ? number_format($data[$a]['refund_count'] / $data[$a]['top_up_success_count'] * 100, 2, '.', '') : 0) . "%";
                $data[$a]['order_user_rate']	            = $data[$a]['success_user_count'] ? number_format($data[$a]['order_count'] / $data[$a]['success_user_count'] , 2 ,'.','')  : 0;
            }

            // 算累加总计数据
            $sum['date'] =  "总计";
            $sum['orgin'] = $platform_arr[$platform];
            foreach ($data as $d) {
                $sum['subscribe_user_count'] += $d['subscribe_user_count'];
                $sum['unsubscribe_user_count'] += $d['unsubscribe_user_count'];
                $sum['user_increase_count'] += $d['user_increase_count'];
                $sum['top_up_success_count'] += $d['top_up_success_count'];
                $sum['refund_count'] += $d['refund_count'];
                $sum['order_count'] += $d['order_count'];
            }
            $sum['refund_up_top_rate']	= ($sum['top_up_success_count'] ? number_format($sum['refund_count'] / $sum['top_up_success_count'] * 100, 2, '.', '') : 0) . "%";
            $sum['order_user_rate']	= $sum['success_user_count'] ? number_format($sum['order_count'] / $sum['success_user_count'] , 2 ,'.','')  : 0;
            $sum['user_operate_success_success']	= ($sum['shop_page_user_count'] ? number_format($sum['success_user_count'] / $sum['shop_page_user_count'] * 100, 2, '.', '') : 0) . "%";
            $count = count($data);

            // 分页显示
            for($x = ($page - 1) * 10,$t = 0;($t < 10 && $x <= $a);$x++,$t++){
                $show_data[$x] = $data[$x];
            }
            unset($_GET['page']);
            $pagehtm = getPages($count, $page - 1,10,"index.php?".http_build_query($_GET));
            if ($_GET['export']) {
                // print_r($data);
                $sheetarray[] = create_excel_column($data, 'date', '日期', $sum['date']);
                $sheetarray[] = create_excel_column($data, 'origin', '来源', $sum['origin']);
                $sheetarray[] = create_excel_column($data, 'subscribe_user_count', '新关注用户数', $sum['subscribe_user_count']);
                $sheetarray[] = create_excel_column($data, 'unsubscribe_user_count', '取消关注用户数', $sum['unsubscribe_user_count']);
                $sheetarray[] = create_excel_column($data, 'user_increase_count', '净增关注用户数', $sum['user_increase_count']);
                $sheetarray[] = create_excel_column($data, 'shop_page_user_count', '借操作人数', $sum['shop_page_user_count']);
                $sheetarray[] = create_excel_column($data, 'top_up_user_count', '付押金人数', $sum['top_up_user_count']);
                $sheetarray[] = create_excel_column($data, 'top_up_success_count', '付押金人次', $sum['top_up_success_count']);
                $sheetarray[] = create_excel_column($data, 'refund_count', '提现人次', $sum['refund_count']);
                $sheetarray[] = create_excel_column($data, 'refund_up_top_rate', '资金提现率', $sum['refund_up_top_rate']);
                $sheetarray[] = create_excel_column($data, 'success_user_count', '租借成功人数', $sum['success_user_count']);
                $sheetarray[] = create_excel_column($data, 'order_count', '租借成功次数', $sum['order_count']);
                $sheetarray[] = create_excel_column($data, 'order_user_rate', '平均每人使用频次（次/人）', $sum['order_user_rate']);
                $sheetarray[] = create_excel_column($data, 'success_fee_user_count', '付费人数', $sum['success_fee_user_count']);
                $sheetarray[] = create_excel_column($data, 'user_operate_success_success', '操作用户转化率', $sum['user_operate_success_success']);

                $sheetarray = transpose($sheetarray);

                export_excel($sheetarray, 'UserData_' .  end($data)['date'] . '_' . $data[0]['date']);
                exit;
            }
            break;

    default:
        # code...
        break;
}
