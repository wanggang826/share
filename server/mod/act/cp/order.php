<?php
use model\Api;

$opt = $opt ? : 'list';

switch ( $opt ) {

	// 站点订单
	case 'list':
	    if (isset($do)) {
	        switch ($do) {
	            # 手动退押金
                case 'return_deposit':
                    // 退款注意事项
                    // 1. tradelog表message字段 里面的refund_fee指的是单个订单已退款的（只用在后台退款，后台退款时会调整为该值）
                    // 2. tradelog表usefee字段 指的是已向用户收取的费用（后台手动退款时会调整该值）
                    // 3. tradelog表refunded字段 指的是已退款的金额（该字段用在提现业务中）
                    // 4. tradelog表price字段  指的是用户支付的押金（目前就是雨伞的押金30元，不分平台）
                    // 5. tradelog表paid字段 指用户在线支付的金额（芝麻信用/账户内支付为0，全款在线支付为30元）

                    $time    = $time ? :date("H:i",time());
                    $date    = $date ? :date("Y-m-d",time());

                    $full_return_operation      = $full_return ? 1 : 0;
                    $part_return_confirm        = $part_return_confirm ? 1 : 0;
                    $money_return_operation     = $money_return ? 1 : 0;
                    $zhima_operation            = $zhima_opt;
                    $ajax_opt = $full_return_operation || $part_return_pre || $part_return_confirm || $money_return_operation || $zhima_operation;
                    LOG::DEBUG ('$_GET array:' .print_r($_GET, true));

                    $order = ct('tradelog')->fetch($orderid);
                    $borrow_station = $order['borrow_station'];
                    if ( !$order ) {
                        LOG::ERROR("can not fetch this order : ".$orderid);
                        exit;
                    }
                    if ($order['tag'] != TAG_UMBRELLA) {
                        LOG::ERROR("this order is not umbrella order, ".$orderid);
                        exit;
                    }
                    if ($order['status'] <= 0) {
                        LOG::INFO("this order not paid");
                        exit;
                    }
                    $platform = $order['platform'];
                    $isZhima = $order['platform'] == PLATFORM_ZHIMA;

                    $stations = DB::fetch_all('SELECT id,title FROM %t', ['jjsan_station']);

                    // 订单撤销, 包括普通和芝麻
                    if($order_cancel) {
                        // 只能撤销借出状态和第一次借出确认状态的订单
                        if (!in_array($order['status'], [ORDER_STATUS_RENT_CONFIRM_FIRST, ORDER_STATUS_RENT_CONFIRM])) {
                            Api::output([], 1, '只能撤销借出状态订单或者第一次确认状态订单');
                            exit;
                        }
                        if($order['platform'] == PLATFORM_ZHIMA) {
                            $zhimaOrder = ct('trade_zhima')->fetch($orderid);
                        }
                        // 更新芝麻订单状态
                        if($zhimaOrder && $zhimaOrder['status'] != ZHIMA_ORDER_CREATE) {
                            Api::output([], 1, '芝麻订单只能撤销创建状态的订单');
                            exit;
                        }

                        if($order['platform'] != PLATFORM_ZHIMA) {
                            if(ct('user')->returnBack($order['uid'], $order['price'], $order['price'])) {
                                // order cancel not need record wallet statement log
                                LOG::DEBUG('success to return money to user account');
                            } else {
                                LOG::ERROR('fail to return money to user account');
                                Api::output([], 1, '撤销失败, 归还押金失败'); exit;
                            }
                        } else {
                            // 待撤销, 定时任务撤销该订单
                            ct('trade_zhima')->update($orderid, ['status' => ZHIMA_ORDER_CANCEL_WAIT, 'update_time'=>time()]);
                            LOG::DEBUG('update zhima order waitting for cancel, orderid: ' . $orderid);
                        }

                        $umbrella = ct('umbrella')->fetch_by_field('order_id', $orderid);
                        if ($umbrella) {
                            ct('umbrella')->update($umbrella['id'], [
                                'status' => UMBRELLA_INSIDE,
                                'station_id' => $order['borrow_station']
                            ]);
                        }

                        $message = unserialize( $order['message'] );
                        if($order['platform'] == PLATFORM_ZHIMA) {
                            $message['refund_fee'] = 0;
                        } else {
                            $message['refund_fee'] = $order['price'];
                        }

                        $refund = round($order['price'], 2);
                        $usefee = 0;
                        // 记录信息
                        $message['manually_return_time'] = time();
                        $message['operator'] = $admin->adminInfo['id'];
                        $res = ct('tradelog')->update($orderid, [
                                'usefee'      => $usefee,
                                'return_station' => $order['borrow_station'],
                                'return_station_name' => $order['borrow_station_name'],
                                'return_shop_id' => $order['borrow_shop_id'],
                                'return_shop_station_id' => $order['borrow_shop_station_id'],
                                'return_city' => $order['borrow_city'],
                                'return_time' => time(),
                                'message'     => serialize($message),
                                'status'      => ORDER_STATUS_RETURN_MANUALLY,
                            ]
                        );

                        if($res){
                            $admin->log_cancel_order($orderid, $order['price'], $order['uid']);
                        }

                        // 推送费用退还信息
                        $msg = [
                            'openid'     => $order['openid'],
                            'orderid'    => $order['orderid'],
                            'refund'     => $isZhima? 0 : $order['price'] //芝麻订单费用为0
                        ];
                        addMsgToQueue($platform, TEMPLATE_TYPE_REFUND_FEE, $msg);

                        LOG::DEBUG('uid: ' . $admin->adminInfo['id'] . " cancel order, orderid: " . $zhima_operation . ',' . $_GET['refund'] . ',' . $orderid);
                        Api::output([], 0, '撤销订单成功');
                        exit;
                    }
                    // 芝麻信用退款
                    // 此退款无接口，只能通过先通过支付宝后台系统退款，再进行系统后台增加退款记录。
                    else if($order['platform'] == PLATFORM_ZHIMA && $zhima_operation) {
                        switch($zhima_operation) {
                            case 'refund':
                                // 退款金额大于已收取的费用
                                if($refund > $order['usefee']) {
                                    Api::output([], 1, '芝麻信用订单的退款金额不能大于已产生费用');
                                    exit;
                                }
                                break;
                            default:
                                Api::output([], 1, '未定义退款');
                                exit;

                        }
                        $zhimaOrder = ct('trade_zhima')->fetch($orderid);
                        // 记录信息
                        $message = unserialize($order['message']);
                        $message['refund_fee'] += $refund;
                        $message['refund_fee'] = round($message['refund_fee'] , 2);
                        $message['manually_return_time'] = time();
                        $message['operator'] = $admin->adminInfo['id'];
                        ct('tradelog')->update($orderid, [
                            'usefee'      => $order['usefee'] - $refund,
                            'message'     => serialize($message),
                            'status'      => ORDER_STATUS_RETURN_EXCEPTION_MANUALLY_REFUND,
                        ]);

                        // 芝麻退款
                        ct('wallet_statement')->insert([
                            'uid' => $order['uid'],
                            'related_id' => $orderid,
                            'type' => WALLET_TYPE_REFUND,
                            'amount' => $refund,
                            'time' => date('Y-m-d H:i:s'),
                        ]);
                        // 推送消息
                        $msg = [
                            'openid'     => $order['openid'],
                            'orderid'    => $order['orderid'],
                            'refund'     => $refund,
                        ];
                        // 强制使用芝麻模板（其实和支付宝是同一个模板）
                        addMsgToQueue(PLATFORM_ZHIMA,  TEMPLATE_TYPE_REFUND_FEE, $msg);
                        $admin->log_return_back($orderid, $refund, $order['uid']);
                        LOG::DEBUG("uid: {$admin->adminInfo['id']} , manually return zhima opt, orderid: $orderid , refund: $refund");
                        Api::output([], 0, '手动退款成功');
                        exit;
                    }

                    // 全额退款
                    if ($full_return_operation) {
                        if ( !in_array($order['status'], [ORDER_STATUS_RENT_CONFIRM, ORDER_STATUS_RENT_CONFIRM_FIRST])) {
                            Api::output([], 1, '订单状态非借出状态，无法全额退押金。');
                            exit;
                        }
                        $time = $_GET['full_return_time'];
                        $return_time = $order['borrow_time'] + $time * 3600;
                        $order_status = $order['status'];
                        $message = unserialize( $order['message'] );
                        $shop_station_info = ct('shop_station')->where(['station_id' => $station])->first();
                        $stationInfo = ct('station')->fetch($station);
                        $shopInfo = ct('shop')->fetch($shop_station_info['shopid']);
                        $title = '';
                        if($shopInfo['name']){
                            $title = $shopInfo['name'];
                        } elseif ($shop_station_info['title']){
                            $title = $shop_station_info['title'];
                        } else {
                            $title = $stationInfo['title'];
                        }

                        if ($message['refund_fee'] == 0) {

                            //事务操作
                            DB::query('begin');
                            $updateUmbrellaResult = ct('umbrella')->update($order['umbrella_id'], [
                                'status' => UMBRELLA_INSIDE,
                                'station_id' => $station
                            ]);
                            $updateTradelogResult = ct('tradelog')->update($orderid, [
                                'status'         => ORDER_STATUS_RETURN_EXCEPTION_MANUALLY_REFUND,
                                'return_time'    => $return_time,
                                'return_station' => $station,
                                'return_station_name' => $title,
                                'return_shop_station_id' => $shop_station_info['id'],
                                'return_shop_id' => $shop_station_info['shopid']
                            ]);
                            if ($order['platform'] == PLATFORM_ZHIMA) {
                                $updateOtherResult = ct('trade_zhima')->update($orderid, [
                                    'status' => ZHIMA_ORDER_COMPLETE_WAIT,
                                    'update_time'=>time()
                                ]);
                            } else {
                                $updateOtherResult = ct('user')->returnBack($order['uid'], $order['price'], $order['price']);
                            }
                            if ($updateUmbrellaResult && $updateTradelogResult && $updateOtherResult ) {
                                DB::query('commit');

                                $msg = [
                                    'openid'     => $order['openid'],
                                    'orderid'    => $order['orderid'],
                                    'refund'     => $isZhima ? 0 : $order['price'],
                                ];
                                addMsgToQueue($platform,  TEMPLATE_TYPE_REFUND_FEE, $msg);

                                $message = unserialize($order['message']);
                                $message['refund_fee'] = round($order['price'], 2);
                                $message['manually_return_time'] = time();
                                $message['operator'] = $admin->adminInfo['id'];
                                ct('tradelog')->update($orderid, ['message' => serialize($message)]);

                                LOG::DEBUG("full return deposit:return all deposit to usablemoney success");
                                $admin->log_return_back($orderid, $order['price'], $order['uid']);
                                Api::output([], 0, '押金退还成功');
                            } else {
                                DB::query('rollback');
                                LOG::ERROR("full return deposit:return deposit fail");
                                Api::output([], 1, '押金不足，无法退款。');
                            }
                        } else {
                            Api::output([], 1, '无法在退过押金的状态下进行全额退押金，请在手动退押金中进行此操作。');
                            LOG::ERROR("full return deposit:change order status fail");
                        }
                        exit;
                    }

                    // 部分退款
                    elseif ($part_return_confirm) {
                        if ( !in_array($order['status'], [ORDER_STATUS_RENT_CONFIRM, ORDER_STATUS_RENT_CONFIRM_FIRST])) {
                            Api::output([], 1, '订单状态非借出状态，无法部分退押金。');
                            exit;
                        }
                        $return_time = strtotime($date . $time);
                        if ($order['borrow_time'] > $return_time) {
                            Api::output([], 1, '归还时间不能小于借出时间');
                            exit;
                        }
                        $usefee = calcFee($order['orderid'], $order['borrow_time'], $return_time);
                        $return_fee = $order['price'] - $usefee;
                        if ($return_fee < 0) {
                            Api::output([], 1, '根据时间计算的退款金额大于订单金额，不可退。');
                            exit;
                        }
                        $shop_station_info = ct('shop_station')->where(['station_id' => $station1])->first();
                        LOG::DEBUG('shop_station_information : ' . print_r($shop_station_info, 1));

                        $stationInfo_1 = ct('station')->fetch($station1);
                        $shopInfo = ct('shop')->fetch($shop_station_info['shopid']);
                        $title = '';
                        if($shopInfo['name']){
                            $title = $shopInfo['name'];
                        } elseif ($shop_station_info['title']){
                            $title = $shop_station_info['title'];
                        } else {
                            $title = $stationInfo_1['title'];
                        }
                        if ($return_fee + $message['refund_fee'] <= $order['price']) {
                            // 事务处理
                            DB::query('begin');
                            $updateTradelogResult = ct('tradelog')->update($orderid, [
                                'status'         => ORDER_STATUS_RETURN_EXCEPTION_MANUALLY_REFUND,
                                'return_time'    => $return_time,
                                'return_station' => $station1,
                                'return_station_name' => $title,
                                'return_shop_station_id' => $shop_station_info['id'],
                                'return_shop_id' => $shop_station_info['shopid']
                            ]);
                            $updateUmbrellaResult = ct('umbrella')->update($order['umbrella_id'], [
                                'status' => UMBRELLA_INSIDE,
                                'station_id' => $station1
                            ]);
                            if ($order['platform'] == PLATFORM_ZHIMA) {
                                $updateOtherResult = ct('trade_zhima')->update($orderid, [
                                    'status' => ZHIMA_ORDER_COMPLETE_WAIT,
                                    'update_time'=>time()
                                ]);
                            } else {
                                $updateOtherResult = ct('user')->returnBack($order['uid'], $return_fee, $order['price']);
                            }
                            if ($updateTradelogResult && $updateUmbrellaResult && $updateOtherResult) {
                                DB::query('commit');

                                $order['usefee'] += ($order['price'] - $return_fee);
                                $message = unserialize($order['message']);
                                $message['refund_fee'] += $return_fee;
                                $message['refund_fee'] = round($message['refund_fee'] , 2);
                                $message['manually_return_time'] = time();
                                $message['operator'] = $admin->adminInfo['id'];
                                ct('tradelog')->update($orderid, ['usefee' => $order['usefee'] , 'message' => serialize($message)]);
                                $msg = [
                                    'openid'     => $order['openid'],
                                    'orderid'    => $order['orderid'],
                                    'refund'     => $return_fee,
                                ];
                                addMsgToQueue($platform,  TEMPLATE_TYPE_REFUND_FEE, $msg);
                                LOG::DEBUG("part return deposit:all success");

                                // 这里退款实际上就是钱包明细支付
                                if ($order['usefee'] > 0) {
                                    // 记录用户流水
                                    ct('wallet_statement')->insert([
                                        'uid' => $order['uid'],
                                        'related_id' => $orderid,
                                        'type' => $order['platform'] == PLATFORM_ZHIMA ? WALLET_TYPE_ZHIMA_PAID_UNCONFIRMED : WALLET_TYPE_PAID,
                                        'amount' => $order['usefee'],
                                        'time' => date('Y-m-d H:i:s'),
                                    ]);
                                    LOG::DEBUG('wallet pay record , orderid: ' . $orderid . ' amount: ' . $order['usefee']);
                                }

                                $admin->log_return_back($orderid, $return_fee, $order['uid']);
                                $order['platform'] == PLATFORM_ZHIMA ? Api::output([], 0, '芝麻信用退款成功： ') : Api::output([], 0, '押金退还成功，退款金额： '. $return_fee);
                            } else {
                                DB::query('rollback');
                                LOG::ERROR("part return deposit:return deposit fail");
                                Api::output([], 1, '退款失败。');
                            }
                        } else {
                            LOG::ERROR("part return deposit: calc fee fail, return_fee: $return_fee , refund_fee: {$message['refund_fee']} , price: {$order['price']}");
                            Api::output([], 1, '订单费用计算失败');
                        }
                        exit;
                    }

                    // 手动退款
                    elseif ($money_return_operation) {

                        $return_fee = $deposit + 0;
                        $message = unserialize($order['message']);

                        if ($return_fee < 0.01) {
                            Api::output([], 1, '退款金额不能少于0.01元');
                            exit;
                        }

                        // 支付过的订单都可以进行退款操作(包括已退款的)
                        // 由于之前归还订单没有在message里面所以进行了以下处理
                        if ($return_fee + $message['refund_fee'] <= $order['price']) {

                            // 事务处理
                            DB::query('begin');

                            $updateTradelogResult = ct('tradelog')->update($orderid, [
                                'return_time' => $order['return_time'] ? : time(),
                                'status' => ORDER_STATUS_RETURN_EXCEPTION_MANUALLY_REFUND,
                                'return_station' => $order['return_station'] ? : $order['borrow_station'],
                                'return_shop_id' => $order['return_shop_id'] ? : $order['borrow_shop_id'],
                                'return_shop_station_id' => $order['return_shop_station_id'] ? : $order['borrow_shop_station_id'],
                                'return_station_name' => $order['return_station_name'] ? : $order['borrow_station_name'],
                                'lastupdate' => time(), // 手动退款，有可能是多次手动退款，所以必须加入lastupdate保证$updateTradelogResult每次都可以执行成功
                            ]);
                            if (in_array($order['status'], [ORDER_STATUS_RENT_CONFIRM, ORDER_STATUS_RENT_CONFIRM_FIRST])) {
                                // 借出状态的订单有押金
                                $deposit = $order['price'];
                                // 借出状态没有usefee, usefee === 0
                                $usefee = $order['price'] - $return_fee;
                            } else {
                                // 非借出状态的订单没有押金
                                $deposit = 0;
                                // 非借出状态有usefee (可能为0, 或者大于0)
                                $usefee = $order['usefee'] - $return_fee;  // $usefee 有可能是负数, 但是数据库里面是UNSIGN, 所以没关系
                            }
                            $updateOtherResult = ct('user')->returnBack($order['uid'], $return_fee, $deposit);
                            if ($updateTradelogResult && $updateOtherResult) {
                                DB::query('commit');
                                //更新雨伞信息
                                $updateUmbrellaResult = ct('umbrella')->update($order['umbrella_id'], ['status' => UMBRELLA_INSIDE]);

                                $message['refund_fee'] += $return_fee;
                                $message['refund_fee'] = round($message['refund_fee'] , 2);
                                $message['manually_return_time'] = time();
                                $message['operator'] = $admin->adminInfo['id'];

                                ct('tradelog')->update($orderid, [
                                    'usefee' => $usefee,
                                    'message' => serialize($message)
                                ]);
                                $msg = [
                                    'openid'     => $order['openid'],
                                    'orderid'    => $order['orderid'],
                                    'refund'     => $return_fee,
                                ];
                                addMsgToQueue($platform,  TEMPLATE_TYPE_REFUND_FEE, $msg);
                                LOG::DEBUG("money return deposit:all success");
                                ct('wallet_statement')->insert([
                                    'uid' => $order['uid'],
                                    'related_id' => $orderid,
                                    'type' => WALLET_TYPE_REFUND,
                                    'amount' => $return_fee,
                                    'time' => date('Y-m-d H:i:s'),
                                ]);
                                $admin->log_return_back($orderid, $return_fee, $order['uid']);
                                Api::output([], 0, '押金退还成功');
                            } else {
                                DB::query('rollback');
                                LOG::ERROR("money return deposit:return deposit fail");
                                Api::output([], 1, '押金退还失败。');
                            }

                        } else {
                            Api::output([], 1, '退款金额超出限制, 订单最多可退款: '. ($order['price'] = $message['refund_fee']));
                            LOG::ERROR("money return deposit:change order three fields fail");
                        }
                        exit;
                    }

                    include template('jjsan:cp/order/refund');
                    exit;
                    break;

                # 订单详情
                // @todo 添加权限控制
                case 'order_detail':

                    $order = ct('tradelog')->fetch($orderid);

                    $order['lastupdate'] = date('Y-m-d H:i:s', $order['lastupdate']);
                    $message = unserialize($order['message']);

                    $message['manually_return_time'] = date("Y-m-d H:i:s" , $message['manually_return_time']);

                    if($order['platform'] == PLATFORM_ZHIMA) {
                        $zhimaOrder = ct('trade_zhima')->fetch($orderid);
                    }
                    include template('jjsan:cp/order/order_detail');
                    break;

                # 用户信息
                case 'buyer_detail':
                    $user = ct('user')->fetch_by_field('id' , $uid);
                    $user['headimg'] = ct('user_info')->getField($uid, 'headimgurl');
                    include template('jjsan:cp/order/buyer_detail');
                    break;

                # 遗失处理
                case 'lost_order_finish':
                    $order = ct('tradelog')->fetch($orderid);
                    LOG::DEBUG('lost order finish order info, ' . print_r($order, 1));
                    if(!in_array($order['status'], [ORDER_STATUS_RENT_CONFIRM, ORDER_STATUS_RENT_CONFIRM_FIRST])) {
                        Api::output([], 1, '非借出状态的订单不能进行遗失处理！');
                        exit;
                    }
                    // 事务处理
                    DB::query('begin');
                    $updateTradelogResult = ct('tradelog')->update($orderid, [
                            'status' => ORDER_STATUS_TIMEOUT_NOT_RETURN,
                            'usefee' => $order['price'],
                            'return_time' => time(),
                            'lastupdate' => time()
                    ]);
                    if($order['platform'] == PLATFORM_ZHIMA){
                        LOG::INFO("zhima order");
                        $updateOtherResult = ct('trade_zhima')->update($orderid, ['status' => ZHIMA_ORDER_COMPLETE_WAIT, 'update_time'=>time()]);
                    } else {
                        $updateOtherResult = ct('user')->reduceDeposit($order['uid'], $order['price']);
                    }
                    if ($updateTradelogResult && $updateOtherResult) {
                        LOG::INFO("lost order finish order success");
                        DB::query('commit');
                        // 记录用户流水
                        ct('wallet_statement')->insert([
                            'uid' => $order['uid'],
                            'related_id' => $orderid,
                            'type' => $order['platform'] == PLATFORM_ZHIMA ? WALLET_TYPE_ZHIMA_PAID_UNCONFIRMED : WALLET_TYPE_PAID,
                            'amount' => $order['price'],
                            'time' => date('Y-m-d H:i:s'),
                        ]);
                        Api::output([], 0, '处理成功!');
                    } else {
                        LOG::ERROR("reduce deposit failed: {$order['orderid']} , reduce deposit: {$order['price']}");
                        DB::query('rollback');
                        Api::output([], 1, '遗失处理失败');
                    }
                    break;
            }
            exit;
        }

        $accessCities = null;
	    $accessShops = null;

	    if (!$auth->globalSearch) {
            $accessCities = $auth->getAccessCities();
            $accessShops = $auth->getAccessShops();
        }

        // 转换商铺名称为商铺id
        if ($b_shop_station_title) {
            $bShopStationInfos = ct('shop_station')
                ->where(['title' => ['value' => '%'.$b_shop_station_title.'%', 'glue' => 'like' ]])
                ->get();
            $_GET['b_shop_sid'] = array_column($bShopStationInfos, 'id');
        }
        if ($r_shop_station_title) {
            $rShopStationInfos = ct('shop_station')
                ->where(['title' => ['value' => '%'.$r_shop_station_title.'%', 'glue' => 'like' ]])
                ->get();
            $_GET['r_shop_sid'] = array_column($rShopStationInfos, 'id');
        }


        $data = ct('tradelog')->search_order($_GET, $page, $pageSize, $accessCities, $accessShops);

        unset($_GET['page']);
        $pagehtm = getPages($data['count'] , $page - 1, RECORD_LIMIT_PER_PAGE, '/index.php?'.http_build_query($_GET));
        break;

    default:
        # code...
}
