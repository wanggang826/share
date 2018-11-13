<?php

# task.php
# 执行在一段时间内的任务，区别crontab

define('DISABLEXSSCHECK', true);
define('PLUGIN_NAME', 'jjsan');
define('JJSAN_DIR_PATH', __DIR__ . '/../');
define('DZ_ROOT', JJSAN_DIR_PATH . '/../../');

// 初始化 discuz 内核对象
require DZ_ROOT . '/class/class_core.php';
$discuz = C::app();
$discuz->init();

// 加载基于PSR0/4规范的类
require_once JJSAN_DIR_PATH . '/vendor/autoload.php';

// $LOG_FILENAME配置log文件名称
$LOG_FILENAME = '_task_' . $_GET['act'];
// 加载配置
require_once JJSAN_DIR_PATH . '/cfg.inc.php';

// 加载类库
require_once JJSAN_DIR_PATH . '/lib/scurl.class.php';
require_once JJSAN_DIR_PATH . '/lib/wxapi.class.php';
require_once JJSAN_DIR_PATH . '/lib/wxpay.class.php';
require_once JJSAN_DIR_PATH . '/lib/lbsapi.class.php';
require_once JJSAN_DIR_PATH . '/lib/alipay/AlipayAPI.php';

// 加载业务函数
require_once JJSAN_DIR_PATH . '/func.inc.php';

// $_GET数据过滤
$_GET = array_map(function($v){
    return is_array($v) ? $v : trim($v);
}, $_GET);
extract($_GET);

if ( $_SERVER['REMOTE_ADDR'] != SERVERIP ) {
	echo 'Illegal Access'; exit;
}

switch ($act) {

    # 这个是针对芝麻活动的页面。
    # 活动时间：8.6-8.12。
    # 领券后，券有效期是15天。
	case 'zhima_activity':
        $time = date('Y-m-d', strtotime("-1 day"));
        $coupon = 'JJ伞代金券';
        $dir = JJSAN_DIR_PATH . '/data/alipay_bill/';
        $filename = $time.'-alipay-bill.zip';
        $path = $dir.$filename;

        $res = AlipayAPI::AlipayDataDataserviceBillDownloadurlQuery($time);

        if($res->code == '10000'){
            try{
                LOG::DEBUG(date('Y-m-d',time())." log start...");
                $content = $res->bill_download_url;
                $res = explode("?",$content);
                $api = $res[0];
                $getData = array();
                foreach(explode("&",$res[1]) as $key => $value){
                    $ret = explode('=',$value);
                    $getData[$ret[0]] = urldecode($ret[1]);
                }
                $scurl = new sCurl( $api, 'GET', $getData );
                $content = $scurl->sendRequest();

                file_put_contents($path, $content);

                $zip = new ZipArchive();
                if($zip->open($path) !== TRUE) {
                    die ("Could not open archive");
                }

                $index0 = $zip->statIndex(0);
                $index1 = $zip->statIndex(1);

                if ($index0['size'] > $index1['size']) {
                    $detailBill = $index0['name'];
                } else {
                    $detailBill = $index1['name'];
                }

                $zip->extractTo($dir);
                $fp = fopen($dir.$detailBill, 'r');

                while ( $content = fgets($fp)) {
                    $content = str_replace('	', '', iconv('gb2312', 'utf-8', $content));
                    $data = explode(',', $content);
                    if(is_numeric($data[0])){
                        // JJ伞代金券
                        if(trim($data[18]) == $coupon){
                            $orderDetails = ct('trade_zhima')->where(['alipay_fund_order_no' => $data[0]])->first();
                            if(!empty($orderDetails)){
                                $insertData = array(
                                    'orderid' => $orderDetails['orderid'],
                                    'zhima_order' => $orderDetails['zhima_order'],
                                    'openid' => $orderDetails['openid'],
                                    'pass_name' => $coupon,
                                    'pass_amount' => $data[17],
                                    'order_amount' => $data[11],
                                    'pay_amount' => $data[12],
                                    'discount_amount' => $data[16],
                                    'alipay_profit' => $data[23],
                                    'alipay_fund_order_no' => $data[0],
                                    'create_time' => strtotime($data[4]),
                                    'finish_time' => strtotime($data[5])
                                );
                                LOG::DEBUG("coupon data: " . json_encode($insertData));
                                DB::query('begin');
                                $billResult = ct('alipay_bill')->insert($insertData, false, false, true);
                                $zhimaResult = ct('trade_zhima')->update($orderDetails['orderid'], ['pay_amount'=>$insertData['pay_amount']]);
                                $tradelogResult = ct('tradelog')->update($orderDetails['orderid'], ['usefee'=>$insertData['pay_amount']]);
                                if ($billResult && $zhimaResult && $tradelogResult) {
                                    LOG::INFO("update alipass info success");
                                    DB::query('commit');
                                } else {
                                    DB::query('rollback');
                                    LOG::WARN(" update alipass info fail, bill result: $billResult , zhima result: $zhimaResult , tradelog result: $tradelogResult");
                                }
                            }else{
                                LOG::DEBUG('can not found alipay_fund_order_no: '.$data[0]);
                            }
                        }
                    }
                }
            }catch(Exception $e){
                print_r($e->getmessage());
            }finally{
                LOG::DEBUG(date('Y-m-d',time())." log end...");
                fclose($fd);
                $zip->close();
            }
        } else {
            LOG::WARN("get alipay bill download url fail, " .print_r($res, 1));
        }
        break;


    # 统计站点相关信息
    case 'count_station_log':
        $stations = ct('station')->get();
        // 判断统计间隔这段时间是否登录过（超过3个小时未更新同步时间就认为该时间段未在线）
        // 登录过就默认在线，槽位数、雨伞数、信号值均未数据库值
        // 未登陆过就默认 槽位数、雨伞数、信号值为0
        $stations = array_map(function($a){
            $a['is_online'] = $a['sync_time'] < time() - 3*3600 ? false : true;
            return $a;
        }, $stations);
        $pre = date('Ymd');
        $t = date('G'); // 24小时制，没有前导零
        $time = time();
        $beginTime = mktime(0, 0, 0, date('m', $time), date('d', $time), date('Y', $time));
        $endTime = $time;
        foreach ($stations as $station) {

            // 必须要清空$data
            $data = [];
            $data['station_id'] = $station['id'];
            $stationLog = ct('station_log')->fetch($pre.$station['id']);

            // 判断是否在线，不在线 槽位数、雨伞数、信号值为0
            if (!$station['is_online']) {
                $station['usable'] = 0;
                $station['rssi'] = 0;
                $station['total'] = 0;
            }

            if (empty($stationLog)) {
                $data['id'] = $pre.$station['id'];
                $shopStation = ct('shop_station')->select('id')->where(['station_id' => $station['id']])->first();
                if ($shopStation) $data['shop_station_id'] = $shopStation['id'];
                $data['created_at'] = time();
                $data['updated_at'] = time();
                $data['rssi_info'] = json_encode([$t => $station['rssi']]);

                $outUmbrellaCnt = ct('umbrella')
                    ->where(['station_id' => $station['id'], 'order_id' => ['value' => 'JJSAN%', 'glue' => 'like']])
                    ->count();
                if ($outUmbrellaCnt) LOG::INFO("station id : {$station['id']} , out umbrella cnt: $outUmbrellaCnt");
                $data['umbrella_from_station'] = json_encode([$t => $station['usable'] + $outUmbrellaCnt]);
                $data['slot_from_station'] = json_encode([$t => $station['total']]);
                $data['max_umbrella_count'] = $station['usable'];
                $data['min_umbrella_count'] = $station['usable'];

                ct('station_log')->insert($data, true);

            } else {
                if (!$stationLog['shop_station_id']) {
                    $shopStation = ct('shop_station')->select('id')->where(['station_id' => $station['id']])->first();
                    if ($shopStation) $data['shop_station_id'] = $shopStation['id'];
                }
                $data['updated_at'] = time();

                // 信号
                $rssiInfo = json_decode($stationLog['rssi_info'], true);
                $rssiInfo[$t] = $station['rssi'];
                $data['rssi_info'] = json_encode($rssiInfo);

                // 槽位
                $slotFromStation = json_decode($stationLog['slot_from_station'], true);
                $slotFromStation[$t] = $station['total'];
                $data['slot_from_station'] = json_encode($slotFromStation);

                // 更新期初雨伞数量
                $outUmbrellaCnt = ct('umbrella')
                    ->where(['station_id' => $station['id'], 'order_id' => ['value' => 'JJSAN%', 'glue' => 'like']])
                    ->count();
                if ($outUmbrellaCnt) LOG::INFO("station id : {$station['id']} , out umbrella cnt: $outUmbrellaCnt");
                $umbrellaFromStation = json_decode($stationLog['umbrella_from_station'], true);
                $umbrellaFromStation[$t] = $station['usable'] + $outUmbrellaCnt;
                $data['umbrella_from_station'] = json_encode($umbrellaFromStation);

                // 更新最大最小雨伞数
                if ($station['is_online']) {
                    // 最大值判断
                    if ($station['usable'] > $stationLog['max_umbrella_count']) {
                        $data['max_umbrella_count'] = $station['usable'];
                    }
                    // 最小值判断（只能粗略判断）
                    if ($stationLog['min_umbrella_count'] == 0) {
                        $data['min_umbrella_count'] = $station['usable'];
                    } else {
                        if ($stationLog['min_umbrella_count'] > $station['usable']) {
                            $data['min_umbrella_count'] = $station['usable'];
                        }
                    }
                }

                ct('station_log')->update($stationLog['id'], $data);
            }
        }
        break;

    # 删掉5天之前的心跳数据
    case 'delete_station_heartbeat_log':
        LOG::DEBUG('begin');
        $endTime = time() - 5 * 24 * 3600; //只保留5天的数据
        $i       = 0;
        while (ct('station_heartbeat_log')->first()['created_at'] < $endTime)
        {
            DB::query('DELETE FROM %t LIMIT 1000', ['jjsan_station_heartbeat_log']);
            usleep(50000); //休息一下
            $i++;
        }
        LOG::INFO("delete about $i k data");
        LOG::DEBUG('end');
        break;

    default:
	    echo 'no task';
}