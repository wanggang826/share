<?php
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
$LOG_FILENAME = "_sync";
// 加载配置
require_once JJSAN_DIR_PATH . '/cfg.inc.php';

// 加载类库
require_once JJSAN_DIR_PATH . '/lib/scurl.class.php';
require_once JJSAN_DIR_PATH . '/lib/wxapi.class.php';
require_once JJSAN_DIR_PATH . '/lib/wxpay.class.php';
require_once JJSAN_DIR_PATH . '/lib/swapi.class.php';

// 加载业务函数
require_once JJSAN_DIR_PATH . '/func.inc.php';

// $_GET数据过滤
$_GET = array_map(function($v){
    return is_array($v) ? $v : trim($v);
}, $_GET);
extract($_GET);

if ($_SERVER['REMOTE_ADDR'] != SERVERIP) {
    echo 'Illegal Access'; exit;
}

$postData = $GLOBALS["HTTP_RAW_POST_DATA"] ? : file_get_contents("php://input");

LOG::DEBUG('get postData:'.$postData);

$res = json_decode($postData, true);
$stationid = $res['stationid'];
$data = $res['data'];
$data['ACT'] = strtolower($data['ACT']);

if (empty($res)) {
    echo json_encode(makeErrorData(ERR_PARAMS_INVALID, 'invalid status'));
    exit;
}

LOG::DEBUG('stationid: ' . $stationid);
LOG::DEBUG('data ' . print_r($data, 1));

// 非登录的请求需要带上stationid
if ($data['ACT'] != 'login') {
    // stationid以data中的stationid为主
    $stationid = $data['STATIONID'];
    $station = ct('station')->fetch($stationid);
    if(!$station) {
        LOG::WARN('station not exist');
        echo json_encode(makeErrorData(ERR_STATION_NEED_LOGIN, 'station is gone, need login'));
        exit;
    }

    $shopStation = ct('shop_station')->where(['station_id' => $stationid])->first();
    $shop = ct('shop') -> fetch($shopStation['shopid']);
    $terminalTime = $data['TIME'];
}

switch ($data['ACT']) {

    # 登录处理
    case 'login':
        LOG::DEBUG('login handle');
        $mac = $data['MAC'];
        if (empty($mac)) {
            LOG::INFO('mac empty, mac: ' . $mac);
            $statusData = [
                'ERRCODE' => 0,
                'ERRMSG' => 'mac not exist',
                'ACK' => 'login',
            ];
            echo json_encode($statusData);exit;
        }
        $station = ct('station')->where(['mac' => $mac])->first();
        LOG::DEBUG('station info ' . print_r($station, 1));
        if (!$station) {
            LOG::WARN('station not exist, mac: ' . $mac);
            $statusData = [
                'ERRCODE' => 0,
                'ERRMSG' => 'mac not exist',
                'ACK' => 'login',
            ];
            echo json_encode($statusData);exit;
        }
        // 更新站点同步时间
        ct('station')->update($station['id'], ['sync_time' => time()]);
        LOG::DEBUG('login success, stationid: ' .$station['id'] . ' mac: ' . $mac);
        // 更新统计表登录次数
        $stationLogId = date('Ymd').$station['id'];
        $stationLog = ct('station_log')->fetch($stationLogId);
        if (empty($stationLog)) {
            ct('station_log')->insert(['id' => $stationLogId, 'login_count' => 1, 'created_at' => time()]);
        } else {
            ct('station_log')->updateLoginCount($stationLogId, 1);
        }

        $rst = [
            'bindaddress' => 1,
            'stationid' => $station['id'],
        ];
        echo json_encode($rst);
        break;

    # 借出确认
    case 'rent_confirm':
        LOG::DEBUG('umbrella rent confirm');
        $orderid = $data['ORDERID'];
        $slot = $data['SLOT'] - UMBRELLA_SLOT_INTERVAL;
        $stationid = $data['STATIONID'];
        $umbrellaid = $data['ID'];
        LOG::DEBUG('orderid: '.$orderid);

        $order = ct('tradelog')->fetch($orderid);
        $uid = $order['uid'];
        $item = $data['umbrella']; // 雨伞信息
        $message = $order['message'] ? unserialize($order['message']) : [];
        $casue = '';
        // @todo 终端传过来的时间未做处理

        // status: 0:push后先回复雨伞ID信息， 1：代表确认用户拿走雨伞 2：代表借出失败(用户未拿走等)
        switch ($data['STATUS']) {
            case 0:
                // 雨伞出伞失败的话，可能会再次发送这个命令过来
                // 或者网络延时导致多次发送相同命令
                if ($order['status'] == ORDER_STATUS_RENT_CONFIRM_FIRST) {
                    $message['slot'] = $slot > 0 ? $slot : 0;
                    ct('tradelog')->update($orderid, [
                        'umbrella_id' => $umbrellaid,
                        'lastupdate' => time(),
                        'message' => serialize($message)
                    ]);
                    LOG::DEBUG('status 0 updated again');
                    echo json_encode(makeErrorData(ERR_NORMAL, 'msg received', $orderid, 'rent_confirm'));
                    exit;
                }
                // 正常流程: 订单状态已支付
                if ($order['status'] == ORDER_STATUS_PAID) {
                    // 第一次借出确认时雨伞ID存在
                    $message['slot'] = $slot > 0 ? $slot : 0;

                    ct('tradelog')->update($orderid, [
                        'borrow_time' => time(),
                        'status' =>  ORDER_STATUS_RENT_CONFIRM_FIRST,
                        'umbrella_id' => $umbrellaid,
                        'lastupdate' => time(),
                        'message' => serialize($message)
                    ]);
                    LOG::DEBUG("orderid: $orderid umbrella_id: $umbrellaid slot: $slot , change status to order_status_confirm_first");

                    // 通知终端服务器接收成功
                    echo json_encode(makeErrorData(ERR_NORMAL, 'msg received', $orderid, 'rent_confirm'));
                    exit;
                }
                //异常订单状态直接回复
                LOG::ERROR("orderid $orderid has an exception status " . print_r($order, 1));
                echo json_encode(makeErrorData(ERR_NORMAL, 'msg received', $orderid, 'rent_confirm'));
                exit;

            case 1:
                // 由于网络延时 需要判断是否已经确认过
                if($order['status'] == ORDER_STATUS_RENT_CONFIRM) {
                    LOG::DEBUG('network problem, status 1 before status 0, it is ok');
                    echo json_encode(makeErrorData(ERR_NORMAL, 'confirm success', $orderid, 'rent_confirm'));
                    exit;
                }

                // 确认借出成功(订单状态可能是借出第一次确认或者借出未取走中间态)
                if(in_array($order['status'], [ORDER_STATUS_RENT_CONFIRM_FIRST, ORDER_STATUS_RENT_NOT_FETCH_INTERMEDIATE])) {
                    // 订单更新
                    $umbrellaInfo = ct('umbrella')->fetch($umbrellaid);
                    // 借出时间服务器为准
                    $borrow_time = time();
                    // 保存借出槽位信息
                    $message['slot'] = $slot;
                    ct('tradelog')-> update($orderid, [
                        'status' =>  ORDER_STATUS_RENT_CONFIRM,
                        'message' => serialize($message),
                        'umbrella_id' => $umbrellaid,
                        'borrow_time' => $borrow_time,
                        'lastupdate' => $borrow_time,
                    ]);

                    // 这里必须保留!!!!
                    // 如果该雨伞id处于非在槽位状态且orderid不为空，则将上一单退还
                    // 分2种情况, 借出状态的订单, 借出后同步的订单
                    // 统一以orderid为准
                    // @todo 这里的还伞和定时任务的还伞可能会触发多次还伞推送(用户账户余额不受影响)
                    if ($umbrellaInfo['order_id']) {
                        // 借出后同步订单
                        if ($umbrellaInfo['status'] == UMBRELLA_OUTSIDE_SYNC && $umbrellaInfo['exception_time']) {
                            $exception_return_time = $umbrellaInfo['exception_time'];
                        // 借出状态订单
                        } elseif ($umbrellaInfo['status'] == UMBRELLA_OUTSIDE) {
                            $exception_return_time = $terminalTime ? : time();
                        }
                        returnBackUmbrella($umbrellaid, $stationid, $slot, $exception_return_time);
                    }

                    // 更新站点状态
                    $usable = $station['usable'] - 1 > 0 ? $station['usable'] - 1 : 0;
                    $empty = $station['empty'] + 1;
                    if(ct('station') -> update($stationid, array('sync_time' => time(), 'usable' => $usable, 'empty' => $empty))){
                        LOG::DEBUG('success to update station umbrella numbers');
                    } else {
                        LOG::WARN('failed to update station umbrella numbers');
                    }
                    // 更新雨伞表信息
                    if(ct('umbrella')->handleRent($umbrellaid, $orderid)){
                        LOG::DEBUG("success to update umbrella info, umbrella id: {$umbrellaid} , orderid: $orderid, stationid: $stationid ");
                    } else {
                        LOG::INFO("fail to update umbrella info, umbrella id: {$umbrellaid} , orderid: $orderid, stationid: $stationid ");
                    }

                    LOG::DEBUG('orderid: ' . $orderid . ' rent success');
                    echo json_encode(makeErrorData(ERR_NORMAL, 'confirm success', $orderid, 'rent_confirm'));

                    $msg = [
                        'openid' => $order['openid'],
                        'orderid' => $orderid,
                        'borrow_time' => $borrow_time,
                        'borrow_station_name' => $order['borrow_station_name'],
                    ];

                    addMsgToQueue($order['platform'], TEMPLATE_TYPE_BORROW_UMBRELLA, $msg);
                    exit;
                }

                //异常订单状态直接回复
                LOG::ERROR("orderid $orderid has an exception status " . print_r($order, 1));
                echo json_encode(makeErrorData(ERR_NORMAL, 'confirm success', $orderid, 'rent_confirm'));
                exit;
            // 3 网络超时,终端会比对借出时间,超过30s,会返回此状态
            case 3:
            // 6 没有雨伞借出
            case 6:
            // 8 借出故障
            case 8:
            // 9 借出未拿走（最终状态）
            case 9:
            // 10 电池电量不足，只能还不能借
            case 10:
            // 11 借出异常，雨伞未被拿走（中间状态），最终状态是：1,8,9）
            case 11:
            // 30 因同步时间不成功而无法执行借订单
            case 30:
            // 31 因上一个订单未完成而无法执行新订单
            case 31:
            // 32 设备重发3次STATUS=0都没有收到平台应答，通知平台取消该订单
            case 32:

            $returnTime = time();
            $usefee = 0;

            if($data['STATUS'] == 3) {
                $cause = '网络超时，押金自动退回账户';
                $status = ORDER_STATUS_TIMEOUT_REFUND;
            }
            else if($data['STATUS'] == 6) {
                $cause = '没有合适的雨伞借出';
                $status = ORDER_STATUS_NO_UMBRELLA;
            }
            else if($data['STATUS'] == 8) {
                $cause = '借出故障';
                $status = ORDER_STATUS_MOTOR_ERROR;
            }
            else if($data['STATUS'] == 9) {
                $cause = '借出未拿走，自动退还';
                $status = ORDER_STATUS_RENT_NOT_FETCH;
            }
            else if($data['STATUS'] == 10) {
                $cause = '电量不足,不予借伞';
                $status = ORDER_STATUS_POWER_LOW;
            }
            else if($data['STATUS'] == 11) {
                $cause = '雨伞未被拿走(中间状态)';
                $status = ORDER_STATUS_RENT_NOT_FETCH_INTERMEDIATE;
            }
            else if($data['STATUS'] == 30) {
                $cause = '同步时间失败';
                $status = ORDER_STATUS_SYNC_TIME_FAIL;
            }
            else if($data['STATUS'] == 31) {
                $cause = '上一单未完成';
                $status = ORDER_STATUS_LAST_ORDER_UNFINISHED;
            }
            else if($data['STATUS'] == 32) {
                $cause = '网络确认无应答';
                $status = ORDER_STATUS_NETWORK_NO_RESPONSE;
            }

            // 中间态确认，只变更订单状态，其他不变
            if ($data['STATUS'] == 11) {

                ct('tradelog')->update($order, [
                    'status' => ORDER_STATUS_RENT_NOT_FETCH_INTERMEDIATE,
                    'lastupdate' => time(),
                ]);
                // 通知终端服务器接收成功
                echo json_encode(makeErrorData(ERR_NORMAL, 'msg received', $orderid, 'rent_confirm'));
                exit;
            }

            // 未借出未成功的, 推送租借失败通知
            // message更新refund_fee, 退还押金为租借价格
            $message['refund_fee'] = $order['price'];
            // 更新订单状态, 统一0收费0借用时间
            $ret = ct('tradelog')->update($orderid, [
                'umbrella_id' => $umbrellaid,
                'status' => $status,
                'return_station' => $stationid,
                'return_time' => $order['borrow_time'], // 相当于0借出时间
                'usefee' => $usefee,
                'lastupdate' => time(),
                'return_shop_id' => $order['borrow_shop_id'],
                'return_shop_station_id' => $order['borrow_shop_station_id'],
                'return_station_name' => $order['borrow_station_name'],
                'return_city' => $order['borrow_city'],
                'return_device_ver' => $order['borrow_device_ver'],
                'message' => serialize($message),
            ]);
            if($ret) {
                LOG::DEBUG('sucess to update to order status');
            } else {
                LOG::ERROR('fail to update to order status');
                echo json_encode(makeErrorData(ERR_SERVER_DB_FAIL, 'umbrella amount rollback fail, db server fail', $orderid, 'rent_confirm'));

                exit;
            }


            // 芝麻订单:撤销订单
            if($order['platform'] == PLATFORM_ZHIMA) {
                // 调用撤销接口撤销该订单
                require_once JJSAN_DIR_PATH . 'lib/alipay/AlipayAPI.php';
                $order = ct('tradelog')->fetch($orderid);
                $zmOrder = ct('trade_zhima')->fetch($orderid);
                $params = [
                    'order_no' => $zmOrder['zhima_order'],
                    'product_code' => 'w1010100000000002858',
                ];
                $resp = AlipayAPI::zhimaOrderRentCancel($params);
                LOG::DEBUG("zhima order: $orderid , cancel result: " . print_r($resp, true));
                if(! empty($resp->code) && $resp->code == 10000) {
                    LOG::DEBUG('zhima order cancel success, orderid: ' . $orderid);
                    ct('trade_zhima')->update($orderid, ['status' => ZHIMA_ORDER_CANCEL_SUCCESS, 'update_time' => time()]);
                } elseif ($resp->code == 40004 && strtoupper($resp->sub_code) == 'ORDER_IS_CANCEL') {
                    // 已经撤销的订单更新芝麻订单状态为已撤销
                    LOG::INFO("order id: $orderid has been cancel in zhima");
                    ct('trade_zhima')->update($orderid, ['status' => ZHIMA_ORDER_CANCEL_SUCCESS, 'update_time' => time()]);
                } else {
                    ct('trade_zhima')->update($orderid,
                        [
                            'status'      => ZHIMA_ORDER_CANCEL_WAIT,
                            'update_time' => time()
                        ]
                    );
                    LOG::ERROR('zhima order cancel fail, orderid: ' . $orderid);
                }
            }

            // 非芝麻订单:押金退回账户余额
            if ($order['platform'] != PLATFORM_ZHIMA) {
                if(ct('user')->returnBack($uid, $order['price'], $order['price'])) {
                    LOG::DEBUG('sucess to return money to user account');
                } else {
                    LOG::ERROR('fail to return money to user account');
                    echo json_encode(makeErrorData(ERR_SERVER_DB_FAIL, 'umbrella amount rollback fail, db server fail', $orderid, 'rent_confirm'));
                    exit;
                }
            }
            $msg = [
                'openid' => $order['openid'],
                'borrow_station_name' => $order['borrow_station_name'],
                'borrow_time' => $order['borrow_time'],
            ];
            addMsgToQueue($order['platform'], TEMPLATE_TYPE_BORROW_FAIL, $msg);
            echo json_encode(makeErrorData(ERR_NORMAL, 'exception handle success', $orderid, 'rent_confirm'));
            exit;

            default:
                echo json_encode(makeErrorData(ERR_PARAMS_INVALID, 'invalid status'));
        }
        exit;

    # 归还确认
    case 'return_back':
        // 归还分为订单归还和新伞归还
        LOG::DEBUG('umbrellas return back');
        $umbrellaid = $data['ID'];
        $slot = $data['SLOT'] - UMBRELLA_SLOT_INTERVAL;

        // 查umbrella表
        $umbrella = ct('umbrella')->fetch($umbrellaid);
        if (!$umbrella) {
            // 新伞,执行入库操作即可
            ct('umbrella')->insert([
                'id' => $umbrellaid,
                'station_id' => $stationid,
                'sync_time' => time(),
                'slot' => $slot,
            ]);
            LOG::INFO("get a new umbrella, umbrella_id: $umbrellaid , station_id: $stationid , slot: $slot");

            // 更新站点库存 @todo 待优化
            $_usable = $station['usable'] + 1;
            $_empty = $station['empty'] - 1 > 0 ? $station['empty'] - 1 : 0;
            ct('station') -> update($stationid, [
                'sync_time' => time(),
                'usable' => $_usable,
                'empty' => $_empty,
            ]);
            LOG::DEBUG('update station umbrellas numbers, station id: ' . $stationid . ' , usable:' . $_usable . ' empty: ' . $_empty);

            echo json_encode(makeErrorData(ERR_NORMAL, 'return umbrella back success', $umbrellaid, 'return_back'));
        } else {
            // 订单归还
            if (time() - $terminalTime > 30) {
                LOG::WARN("this station maybe power down, stationid: $stationid , umbrella: $umbrellaid , terminal time: $terminalTime");
            }
            echo returnBackUmbrella($umbrellaid, $stationid, $slot, $terminalTime ? : time());
        }

        break;

    # 同步配置
    case 'sync_setting':
        LOG::INFO('handle sync setting');
        $stationid = $data['STATIONID'];
        $deviceVer = $data['DEVICE_VER'];
        $softVer   = $data['SOFT_VER'];

        // 更新硬件和软件版本号，由于与login命令时间上是相差很近，有时会更新失败。
        $res = ct('station')->update($stationid, [
            'soft_ver' => $softVer,
            'device_ver' => $deviceVer,
            'sync_time' => time(),
        ]);
        // 返回站点的配置信息 为空返回默认配置 有值返回设定配置
        $settings = ct('station_settings')->getUsingSetting($station['station_setting_id']);

        $reply['TIME']             = time();
        $reply['IP']               = $settings['ip'];
        $reply['DOMAIN']           = $settings['domain'];
        $reply['PORT']             = $settings['port'];
        $reply['HEARTBEAT']        = $settings['heartbeat'];
        $reply['CHECKUPDATEDELAY'] = $settings['checkupdatedelay'];
        if (isset($settings['soft_ver']) && !empty($settings['soft_ver'])) $reply['SOFT_VER'] = $settings['soft_ver'];
        if (isset($settings['file_name']) && !empty($settings['file_name'])) $reply['FILE_NAME'] = $settings['file_name'];

        LOG::DEBUG('sync setting success,  station id: '. $stationid);
        echo json_encode($reply);
        break;

    # 同步雨伞
    case 'sync_umbrella':
        LOG::DEBUG('handle sync umbrella');
        $stationid = $data['STATIONID'];
        $usableUmbrella = $data['USABLE_UMBRELLA'];
        $emptySlotCount = $data['EMPTY_SLOT_COUNT'];
        $lastSyncUmbrellaTime = $data['LASTTIME']; //最近一次同步雨伞的时间

        // 空槽
        if (isset($data['EMPTY_SLOT_COUNT'])) {
            $content['empty'] = $data['EMPTY_SLOT_COUNT'];
        }

        // 可接伞数量
        if (isset($data['USABLE_UMBRELLA'])) {
            $content['usable'] = $data['USABLE_UMBRELLA'];
        }

        // 槽位数量
        $content['total'] = $content['empty'] + $content['usable'];

        $content['sync_time'] = time();

        LOG::DEBUG('sync umbrella stationid: ' . $stationid);
        $stationInfo = ct('station') -> fetch($stationid);
        $slotstatus = $stationInfo['slotstatus'];
        $slotstatus = substr($slotstatus, 0, $content['total']);
        // 处理雨伞信息
        foreach ($data as $k => $uminfo) {
            if(strpos( $k, 'UM') !== 0) continue;
            $cmp = '0000000000';
            $slot = (int)(substr( $k, 2, 2)) - UMBRELLA_SLOT_INTERVAL;
            $status = hexdec(substr($uminfo, 10)); //16进制转10进制
            $slotstatus = substr_replace($slotstatus, $status, $slot -1 , 1);
            $umid = substr($uminfo, 0, 10);
            if($umid == $cmp) {
                if ($status == 3) {
                    // 刷新记录
                    ct('station_slot_log')->deleteStationSlotLog($stationid, $slot);
                    ct('station_slot_log')->insert([
                        'station_id' => $stationid,
                        'slot' => $slot,
                        'type' => 3,
                        'last_sync_umbrella_time' => $lastSyncUmbrellaTime,
                        'create_time' => time(),
                    ]);
                }
                continue;
            }
            if ($status != 4) { //通信中断
                $needCleanLogSlots[] = $slot;
            }
            $umbrella = ct('umbrella')->fetch($umid);
            if($umbrella){
                // 判断雨伞当前状态并做出相应处理
                // 雨伞借出状态且order_id存在时,为借出后同步

                // 区分断电和正常网络延时 小于30s为网络延时, 超过30s为断电

                // 断电处理:最后一次同步雨伞后产生的订单0元处理, 之前同步产生的订单归还时间为最后一次同步时间
                // 非断电情况:归还时间为当前时间
                // @todo 订单0元处理还没做

                if($umbrella['status'] == UMBRELLA_OUTSIDE && !empty($umbrella['order_id'])) {
                    // 存在这样一种可能性
                    // 借出雨伞的过程中同步了雨伞
                    // 目前终端状态0,1之间的最长时间大约12s

                    // 12s内认为是借伞过程中同步了雨伞
                    if (time() - $umbrella['sync_time'] < 12) {
                        // 雨伞状态不做更新
                        LOG::INFO("sync umbrella in borrowing umbrella case, don't update this umbrella sync time");
                        LOG::INFO("umbrella info, " . print_r($umbrella, 1));
                    } else {

                        // 服务器时间
                        $serverTime = time();
                        // 雨伞同步固定时间
                        $umbrellaSyncTime = UMBRELLA_SYNC_TIME;
                        // 网络延时时间
                        $networkDelayTime = 20;
                        // 终端与服务器同步时间差
                        $diffTime = 10;

                        // 判断是否断电:距离上次同步雨伞超过1830秒为断电
                        if ($serverTime > $lastSyncUmbrellaTime + $umbrellaSyncTime + $networkDelayTime + $diffTime) {
                            // 断电:异常时间以上次同步雨伞时间为准
                            $exception_time = $lastSyncUmbrellaTime ? : $serverTime;
                            LOG::INFO('Power down case !!!');
                        } else {
                            // 其他情况:以服务器当前时间为准
                            $exception_time = $serverTime;
                        }

                        ct('umbrella')->update($umid, [
                            'station_id' => $stationid,
                            'status' => UMBRELLA_OUTSIDE_SYNC,
                            'exception_time' => $exception_time,
                            'sync_time' => time(),
                            'slot' => $slot
                        ]);
                        $order = ct('tradelog')->fetch($umbrella['order_id']);
                        LOG::DEBUG("Exception umbrella outside sync, id: {$umbrella['id']} , orderid: {$umbrella['order_id']} , order status: {$order['status']}");
                    }

                } else {

                    // 区分雨伞是否已经有记录异常时间
                    // 有过异常记录的只更新同步时间
                    if ($umbrella['status'] == UMBRELLA_OUTSIDE_SYNC && $umbrella['exception_time'] && $umbrella['order_id']) {
                        LOG::INFO("An exception umbrella hasn't been handled, umbrella info: " . print_r($umbrella, 1));
                        ct('umbrella') -> update($umid, [
                            'sync_time' => time()
                        ]);
                    } else {
                        ct('umbrella') -> update($umid, [
                            'station_id' => $stationid,
                            'sync_time' => time(),
                            'order_id' => '',
                            'status' => UMBRELLA_INSIDE,
                            'slot' => $slot
                        ]);
                    }
                }
            } else {
                // 不存在的umid 插入新表
                ct('umbrella') -> insert([
                    'id' => $umid,
                    'station_id' => $stationid,
                    'sync_time' => time(),
                    'slot' => $slot
                ]);
                LOG::INFO('new umbrella id, id: ' . $umid . ' from station: ' . $stationid . ' slot: ' . $slot);

            }
        }

        // 清除station_slot_log里面的异常记录
        ct('station_slot_log')->deleteStationSlotLog($stationid, $needCleanLogSlots);

        $content['slotstatus'] = $slotstatus;

        ct('station')->update($stationid, $content);

        $reply = [
            'ERRCODE' => 0,
            'ERRMSG' => 'success',
            'ACK' => 'sync_umbrella',
        ];
        LOG::DEBUG('umbrellas sync success, station id: '. $stationid);
        echo json_encode($reply);
        break;

    # 心跳包
    case 'heartbeat':
        LOG::DEBUG('handle heartbeat, stationid: ' . $stationid);
        // 添加心跳log记录
        // @todo 暂时移除掉
        //ct('station_heartbeat_log')->heartbeat($stationid);

        // 更新站点同步时间
        ct('station')->update($stationid, [
            'sync_time' => time(),
            'voltage' => $data['VOLTAGE'],
            'isdamage' => $data['ISDAMAGE'],
            'status' => $data['STATUS'],
            'rssi' => $data['2G_RSSI'],
            'drivemsg' => $data['DRIVEMSG'],
        ]);

        // 更新统计表登录次数
        $stationLogId = date('Ymd').$stationid;
        $stationLog = ct('station_log')->fetch($stationLogId);
        if (empty($stationLog)) {
            ct('station_log')->insert(['id' => $stationLogId, 'heartbeat_count' => 1, 'online_time' => 1,'created_at' => time()]);
        } else {
            ct('station_log')->updateHeartbeatCount($stationLogId, 1);
        }

        // 检查终端是否需要校时
        // 终端比服务器快或者终端比服务器慢25秒以上时，重新校时
        if ($terminalTime - time() > 0 || time() - $terminalTime >= 25) {
            LOG::INFO('station local time need update, the difference: ' . (time() - $terminalTime));
            echo json_encode(makeErrorData(ERR_STATION_NEED_SYNC_LOCAL_TIME, 'station local time need update'));
            exit;
        }

        $reply = [
            'ERRCODE' => 0,
            'ERRMSG' => 'success',
            'ACK' => 'heartbeat',
        ];
        LOG::DEBUG('station heartbeat updated');
        echo json_encode($reply);
        exit;

    # 升级请求
    case 'upgrade_request_file':
        $softVer = $data['SOFT_VER'];
        $fileName = $data['FILE_NAME'];
        // 检查站点配置中soft_ver和file_name和请求的是否一致
        $stationSetting = ct('station_settings')->getUsingSetting($station['station_setting_id']);
        if (!$softVer || $softVer != $stationSetting['soft_ver']) {
            $reply = [
                'ERRCODE' => ERR_STATION_UPGRADE_SOFT_VERSION_MISMATCH,
                'ERRMSG' => 'upgrade soft version mismatch',
                'ACK' => 'upgrade_request_file'
            ];
            echo json_encode($reply);
            LOG::INFO("upgrade soft version mismatch, local: {$stationSetting['soft_ver']} , request: $softVer");
            exit;
        }
        if (!$fileName || $fileName != $stationSetting['file_name']) {
            $reply = [
                'ERRCODE' => ERR_STATION_UPGRADE_FILENAME_MISMATCH,
                'ERRMSG' => 'upgrade filename mismatch',
                'ACK' => 'upgrade_request_file'
            ];
            echo json_encode($reply);
            LOG::INFO("upgrade filename mismatch, local: {$stationSetting['file_name']} , request: $fileName");
            exit;
        }
        if (!file_exists(SOFT_FILE_PATH . $fileName)) {
            $reply = [
                'ERRCODE' => ERR_STATION_UPGRADE_SERVER_FILE_NOT_EXISTED,
                'ERRMSG' => 'upgrade filename server file not existed',
                'ACK' => 'upgrade_request_file'
            ];
            echo json_encode($reply);
            LOG::INFO("upgrade filename server file not existed, file path: " . SOFT_FILE_PATH . "$fileName");
            exit;
        }
        $reply = [
            'SOFT_VER' => $softVer,
            'FILE_NAME' => $fileName,
            'FILE_SIZE' => dechex(filesize(SOFT_FILE_PATH . $fileName))
        ];
        echo json_encode($reply);
        LOG::INFO("upgrade_request_file success, ".print_r($reply, 1));
        break;

    # 升级确认(步骤1)
    case 'upgrade_confirm':
        LOG::INFO('handle upgrade confirm');
        $status = $data['STATUS'];
        switch ($status) {
            // 目前就一个状态0
            case '0':
            default:
                $reply = [
                    'ERRCODE' => 0,
                    'ERRMSG' => 'success',
                    'ACK' => 'upgrade_confirm'
                ];
        }
        echo json_encode($reply);
        break;

    # 升级请求(步骤2~n)
    case 'upgrade_request':
        // 注意: 这里不检查filename是不是这个站点对应station_setting_id里面的数据
        LOG::INFO('handle upgrade request');
        $fileName = $data['FILE_NAME'];
        // 下面2个参数均为16进制的
        $index = $data['INDEX'];
        $byteNUmber  = $data['BYTE_NUMBER'];
        if (!$fileName) {
            $reply = [
                'ERRCODE' => ERR_STATION_UPGRADE_FILENAME_NOT_EXISTED,
                'ERRMSG' => 'upgrade filename not existed',
                'ACK' => 'upgrade_request'
            ];
            echo json_encode($reply);
            LOG::INFO('upgrade filename not existed');
            exit;
        }
        $file = SOFT_FILE_PATH . $fileName;
        $start = hexdec($index);
        $len = hexdec($byteNUmber);
        if (!$start) {
            LOG::INFO("station id $stationid upgrade file $fileName beginning");
        }
        if (!file_exists($file)) {
            $reply = [
                'ERRCODE' => ERR_STATION_UPGRADE_SERVER_FILE_NOT_EXISTED,
                'ERRMSG' => 'upgrade filename server file not existed',
                'ACK' => 'upgrade_request'
            ];
            echo json_encode($reply);
            LOG::WARN("station id $stationid server file not existed, file path: $file");
            exit;
        }
        if (!$len) {
            $reply = [
                'ERRCODE' => ERR_STATION_UPGRADE_BYTE_NUMBER_NOT_EXISTED,
                'ERRMSG' => 'upgrade filename byte number not existed',
                'ACK' => 'upgrade_request'
            ];
            echo json_encode($reply);
            LOG::INFO("upgrade filename byte number not existed");
            exit;
        }
        $handle      = fopen($file, 'r');
        fseek($handle, $start);
        $file_content = fread($handle, $len);
        fclose($handle);
        // 16进制内容
        $content = bin2hex($file_content);
        $reply = [
            'FILE_NAME' => $fileName,
            'INDEX' => $index,
            'BYTE_NUMBER' => $byteNUmber,
            'CONTENT' => $content,
        ];
        echo json_encode($reply);
        LOG::INFO("station id $stationid upgrade content success, index: $index , byte number: $byteNUmber , content: $content");
        break;

    # 升级结束确认(最后步骤)
    case 'upgrade_end':
        LOG::INFO('handle upgrade end');
        $reply = [
            'ERRCODE' => 0,
            'ERRMSG' => 'success',
            'ACK' => 'upgrade_end'
        ];
        LOG::INFO("station id $stationid upgrade file {$data['FILE_NAME']} end");
        echo json_encode($reply);
        break;

    # 锁住槽位
    case 'slot_lock':
        LOG::INFO('handle slot lock');
        LOG::INFO("slot lock info, stationid: $stationid , sceneid: {$station['sceneid']} , slot: {$data['SLOT']} result: {$data['STATUS']}");
        $reply = [
            'ERRCODE' => 0,
            'ERRMSG' => 'success',
            'SLOT' => $data['SLOT'],
            'ACK' => 'slot_lock'
        ];
        echo json_encode($reply);
        break;

    # 解锁槽位
    case 'slot_unlock':
        LOG::INFO('handle slot unlock');
        LOG::INFO("slot unlock info, stationid: $stationid , sceneid: {$station['sceneid']} , slot: {$data['SLOT']} , result: {$data['STATUS']}");
        $reply = [
            'ERRCODE' => 0,
            'ERRMSG' => 'success',
            'SLOT' => $data['SLOT'],
            'ACK' => 'slot_unlock'
        ];
        echo json_encode($reply);
        break;

    # 查询消息
    case 'query_confirm':
        LOG::INFO('handle query confirm');
        LOG::INFO("slot info, stationid: $stationid , slot: {$data['SLOT']} , umbrella: {$data['ID']}");
        $reply = [
            'ERRORCODE' => 0,
            'ERRORMSG' => 'success',
            'SLOT' => $data['SLOT'],
            'ACK' => 'query_confirm'
        ];
        echo json_encode($reply);
        break;

    # 人工借出
    case 'popup_confirm':
        LOG::INFO('handle popup confirm');
        LOG::INFO("popup info, stationid: $stationid , slot: {$data['SLOT']} , status: {$data['STATUS']}");
        $reply = [
            'ERRORCODE' => 0,
            'ERRORMSG' => 'success',
            'SLOT' => $data['SLOT'],
            'ACK' => 'popup_confirm'
        ];
        echo json_encode($reply);
        break;

    # 人工重启
    case 'reboot':
        LOG::INFO('handle device reboot');
        LOG::INFO("reboot info, stationid: $stationid , status: {$data['STATUS']} ");
        $reply = [
            'ERRORCODE' => 0,
            'ERRORMSG' => 'success',
            'ACK' => 'reboot'
        ];
        echo json_encode($reply);
        break;

    # 模组个数
    case 'module_set':
        LOG::INFO('handle module set');
        LOG::INFO("module info, stationid: $stationid , status: {$data['STATUS']}");
        $reply = [
            'ERRORCODE' => 0,
            'ERRORMSG' => 'success',
            'ACK' => 'Module_Set'
        ];
        echo json_encode($reply);
        break;

    # 雨伞数量更新
    case 'sync_cnt':
        LOG::DEBUG('handle sync umbrella count');
        LOG::INFO("stationid: $stationid , usable: {$data['USABLE_UMBRELLA']} , empty: {$data['EMPTY_SLOT_COUNT']}");
        ct('station')->update($stationid, [
            'usable' => $data['USABLE_UMBRELLA'],
            'empty' => $data['EMPTY_SLOT_COUNT'],
            'total' => $data['USABLE_UMBRELLA'] + $data['EMPTY_SLOT_COUNT'],
        ]);
        $reply = [
            'ERRORCODE' => 0,
            'ERRORMSG' => 'success',
            'ACK' => 'sync_cnt'
        ];
        echo json_encode($reply);
        break;

    # 初始化机器（清除槽位异常状态）
    case 'init_set':
        LOG::DEBUG('handle init set');
        $reply = [
            'ERRORCODE' => 0,
            'ERRORMSG' => 'success',
            'ACK' => 'INIT_SET'
        ];
        echo json_encode($reply);
        break;

    # 机器相关功能开启
    case 'module_open':
        LOG::DEBUG('module open');
        if ($data['STATUS'] == 0) {
            LOG::DEBUG("stationid: $stationid , module : {$data['MODULE']} , open success");
        } else {
            LOG::WARN("stationid: $stationid , module : {$data['MODULE']} , open fail");
        }
        $reply = [
            'ERRORCODE' => 0,
            'ERRORMSG' => 'success',
            'ACK' => 'module_open'
        ];
        echo json_encode($reply);
        break;

    # 机器相关功能关闭
    case 'module_close':
        LOG::DEBUG('module close');
        if ($data['STATUS'] == 0) {
            LOG::DEBUG("stationid: $stationid , module : {$data['MODULE']} , close success");
        } else {
            LOG::WARN("stationid: $stationid , module : {$data['MODULE']} , close fail");
        }
        $reply = [
            'ERRORCODE' => 0,
            'ERRORMSG' => 'success',
            'ACK' => 'module_close'
        ];
        echo json_encode($reply);
        break;


    default :
        echo json_encode(makeErrorData(ERR_PARAMS_INVALID, 'invalid parameter 1'));
        exit;
}