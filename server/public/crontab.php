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
$LOG_FILENAME = '_crontab_' . $_GET['act'];
// 加载配置
require_once JJSAN_DIR_PATH . '/cfg.inc.php';

// 加载类库
require_once JJSAN_DIR_PATH . '/lib/scurl.class.php';
require_once JJSAN_DIR_PATH . '/lib/wxapi.class.php';
require_once JJSAN_DIR_PATH . '/lib/wxpay.class.php';
require_once JJSAN_DIR_PATH . '/lib/weapi.class.php';
require_once JJSAN_DIR_PATH . '/lib/lbsapi.class.php';
require_once JJSAN_DIR_PATH . '/lib/alipay/AlipayAPI.php';

// 加载业务函数
require_once JJSAN_DIR_PATH . '/func.inc.php';

// $_GET数据过滤
$_GET = array_map(function ($v) {
    return is_array($v) ? $v : trim($v);
}, $_GET);
extract($_GET);

if ($_SERVER['REMOTE_ADDR'] != SERVERIP) {
    echo 'Illegal Access';
    exit;
}

switch ($act) {

    # 已支付的订单,第一次借出确认   超时退款 每30s执行一次
    case 'order_confirm':
        $timeout             = 60; //60s
        $where['status']     = ORDER_STATUS_PAID;
        $where['lastupdate'] = ['value' => time() - $timeout, 'glue' => '<'];
        $where['tag']        = TAG_UMBRELLA;
        $paidOrders          = ct('tradelog')->where($where)->get();
        // 更新退款
        foreach ($paidOrders as $order) {
            // 雨伞退款到账户余额
            // 更新订单状态, 借还均在原地
            // 非芝麻订单 退全款记录在message中
            $message = $order['message'] ? unserialize($order['message']) : '';
            if ($order['platform'] != PLATFORM_ZHIMA) {
                $message['refund_fee'] = $order['price'];
            }

            $ret = ct('tradelog')->update($order['orderid'], [
                'message'                => $message ? serialize($message) : '',
                'return_station'         => $order['borrow_station'],
                'return_station_name'    => $order['borrow_station_name'],
                'return_shop_id'         => $order['borrow_shop_id'],
                'return_shop_station_id' => $order['borrow_shop_station_id'],
                'return_city'            => $order['borrow_city'],
                'return_device_ver'      => $order['borrow_device_ver'],
                'return_time'            => $order['borrow_time'],
                'lastupdate'             => time(),
                'usefee'                 => 0,
                'status'                 => ORDER_STATUS_TIMEOUT_REFUND,
            ]);
            if ($ret) {
                LOG::DEBUG('update order list success, orderid . ' . $order['orderid']);
                // 退还押金到账户余额
                if ($order['platform'] != PLATFORM_ZHIMA) {
                    if (ct('user')->returnBack($order['uid'], $order['price'], $order['price'])) {
                        LOG::DEBUG('success to return money to user account');
                    } else {
                        LOG::ERROR('fail to return money to user account');
                        break; //当前订单更新失败，跳到下一个订单执行
                    }
                    $deposit = $order['price'];
                } else {
                    // 待撤销, 定时任务撤销该订单
                    ct('trade_zhima')->update($order['orderid'], [
                        'status'      => ZHIMA_ORDER_CANCEL_WAIT,
                        'update_time' => time(),
                    ]);
                    LOG::DEBUG('update zhima order waitting for cancel, orderid: ' . $order['orderid']);
                    $deposit = 0;
                }

                // 推送消息: 租借失败通知
                $msg = [
                    'openid'              => $order['openid'],
                    'borrow_station_name' => $order['borrow_station_name'],
                    'borrow_time'         => $order['borrow_time'],
                ];
                addMsgToQueue($order['platform'], TEMPLATE_TYPE_BORROW_FAIL, $msg);
            } else {
                LOG::ERROR('update  order list fail, orderid: ' . $order['orderid']);
            }
        }
        exit;

    # 处理提现申请 每60s执行一次
    case 'refund':
        LOG::DEBUG('start to refund');
        $refundLogs = ct('refund_log')->where(['status' => REFUND_STATUS_REQUEST])->get();
        if (empty($refundLogs)) {
            echo 'finish';
            LOG::DEBUG('empty, finish refund task');
            exit;
        }
        foreach ($refundLogs as $r) {
            LOG::DEBUG('refund log id:' . $r['id'] . ', refund:' . $r['refund'] . ', refunded:' . $r['refunded'] . ', user id :' . $r['uid']);
            if (round($r['refund'], 2) == round($r['refunded'], 2)) {
                ct('refund_log')->update($r['id'], ['status' => REFUND_STATUS_DONE]);
                LOG::DEBUG('update status, need not refund');
                ct('wallet_statement')->updateTypeByRelatedId($r['id'], [
                    'type' => WALLET_TYPE_WITHDRAW,
                    'time' => date('Y-m-d H:i:s'),
                ]);
                continue;
            }
            $uid = $r['uid'];
            $ret = refund($uid, $r['refund'] - $r['refunded']);
            if ($ret['refunded']) {
                $refundedNow = round($ret['refunded'] + $r['refunded'], 2);
                $refundTotal = round($r['refund'], 2);
                $status      = $refundedNow >= $refundTotal ? REFUND_STATUS_DONE : REFUND_STATUS_REQUEST;
                $detail      = empty($r['detail']) ? $ret['detail'] : array_merge(json_decode($r['detail'], true), $ret['detail']);
                $detail      = json_encode($detail);
                if (ct('refund_log')->update($r['id'], [
                    'status'      => $status,
                    'refunded'    => $refundedNow,
                    'detail'      => $detail,
                    'refund_time' => time(),
                ])) {
                    if ($status == REFUND_STATUS_DONE) {
                        LOG::INFO('refund over, uid: ' . $uid);
                        ct('wallet_statement')->updateTypeByRelatedId($r['id'], [
                            'type' => WALLET_TYPE_WITHDRAW,
                            'time' => date('Y-m-d H:i:s'),
                        ]);
                    }
                } else {
                    LOG::ERROR('update refund log status failed');
                }
            } else {
                LOG::ERROR('refund error, log id:' . $r['id']);
            }
        }
        LOG::DEBUG('finish to refund');
        echo 'finish';
        break;

    # 处理过期未支付的订单 每小时执行一次
    case 'clear_not_pay_order':
        $ret = ct('tradelog')->deleteNotPaidOrder();
        if (!$ret) {
            LOG::INFO('delete not pay order fail or nothing');
        } else {
            LOG::DEBUG('delete not pay order success, count:' . $ret);
        }
        exit;

    # 提醒用户归还 每小时执行一次 //暂时不引入 ORDER_STATUS_RETURN_REMIND 给移除了
    case 'return_remind':
        $curTime = time();
        LOG::DEBUG('return remind start');
        $orders = DB::fetch_all('SELECT orderid,openid,borrow_time,price,platform FROM %t WHERE %i AND %i', [
            'jjsan_tradelog',
            DB::field('status', [ORDER_STATUS_RENT_CONFIRM, ORDER_STATUS_RENT_CONFIRM_FIRST]),
            DB::field('remind', 0),
        ]);

        // 更新退款
        if ($orders) {
            foreach ($orders as $order) {
                $orderid     = $order['orderid'];
                $openid      = $order['openid'];
                $borrow_time = $order['borrow_time'];
                $usefee      = calcFee($orderid, $borrow_time, $curTime);
                $remindPrice = $order['price'] / 5 * 2;
                if ($usefee < $remindPrice) continue;
                if ($usefee > $order['price']) $usefee = $order['price'];
                // 推送消息
                $msg = [
                    'openid'   => $openid,
                    'orderid'  => $orderid,
                    'difftime' => ($curTime - $order['borrow_time']),
                    'usefee'   => $usefee,
                ];
                addMsgToQueue($order['platform'], TEMPLATE_TYPE_RETURN_REMIND, $msg);
                if (!ct('tradelog')->update($orderid, ['remind' => 1, 'lastupdate' => time()])) {
                    LOG::ERROR("update order remind status fail, orderid: {$order['orderid']}");
                } else {
                    LOG::INFO("remind success, orderid: {$order['orderid']}");
                }
            }
        }
        LOG::DEBUG('return remind end');
        break;

    # 根据站点同步时间,调整百度lbs上站点的启用状态 暂不启用
    case 'auto_switch_status':
        LOG::DEBUG('auto_switch_status starts');
        require_once JJSAN_DIR_PATH . '/lib/lbsapi.class.php';
        $shop_station_info = DB::fetch_all('SELECT id, station_id, lbsid, status FROM %t WHERE %i AND %i', [
            'jjsan_shop_station',
            DB::field('lbsid', 0, '>'),
            DB::field('station_id', 0, '>'),
        ]);
        $station_ids       = array_column($shop_station_info, 'station_id');
        $status_list       = array_column($shop_station_info, 'status', 'id');
        $station_list      = DB::fetch_all('SELECT sync_time, id FROM %t WHERE %i', [
            'jjsan_station',
            DB::field('id', $station_ids),
        ]);
        $systemSettings    = json_decode(C::t('common_setting')->fetch('jjsan_system_settings'), true);
        $checkSyncTime     = time() - $systemSettings['checkupdatedelay'];
        $new_enable        = array_column($shop_station_info, 'station_id', 'id');
        $station_sync_list = array_column($station_list, 'sync_time', 'id');
        $lbsdata           = lbsAPI::searchAllPOI('', '', '', 10);
        $total             = $lbsdata['total'];
        $round             = ceil($total / 50);
        foreach ($new_enable as $key => &$value) {
            $value = $station_sync_list[$value] < $checkSyncTime ? 2 : 1;
        }
        $all_lbsid = array_column($shop_station_info, 'lbsid', 'id');
        for ($i = 0; $i < $round; $i++) {
            $lbsdata         = lbsAPI::searchAllPOI('', '', $i, 50);
            $all_old_enable  = array_column($lbsdata['contents'], 'enable', 'sid');
            $old_enable      = array_filter($all_old_enable, function ($var) {
                return ($var != 0);
            });
            $need_update_sid = array_diff_assoc($old_enable, $new_enable);
            $update_lbsid    = array_intersect_key($all_lbsid, $need_update_sid);
            foreach ($update_lbsid as $key => $val) {
                if (!$status_list[$key]) {
                    $ret[] = lbsAPI::updatePOI(['id' => $val, 'enable' => 0]);
                } else {
                    $ret[] = lbsAPI::updatePOI(['id' => $val, 'enable' => $new_enable[$key]]);
                }
            };
        }
        LOG::DEBUG('auto_switch_status ends' . print_r($ret, 1));
        break;

    # 处理雨伞借出后同步 每小时执行一次
    case 'handle_umbrella_exception':
        // 定时检查借出后同步的雨伞, 在异常时间点后一个小时就给对应的订单退款
        $where['status']         = UMBRELLA_OUTSIDE_SYNC;
        $where['exception_time'] = ['value' => time() - 3600, 'glue' => '<'];
        $umbrellas               = ct('umbrella')->where($where)->get();
        if (!$umbrellas) {
            LOG::INFO('no exception umbrella one hour before');
            exit;
        }
        LOG::INFO('exception umbrella, ' . print_r($umbrellas, 1));
        $borrowStatus = [ORDER_STATUS_RENT_CONFIRM, ORDER_STATUS_RENT_CONFIRM_FIRST];
        foreach ($umbrellas as $b) {
            if (empty($b['order_id'])) {
                continue;
            }

            $orderInfo = ct('tradelog')->fetch($b['order_id']);
            LOG::INFO("orderid {$b['order_id']} , status: {$orderInfo['status']}");
            if (in_array($orderInfo['status'], $borrowStatus)) {

                // 归还时间定义: 有异常时间以异常时间为准, 没有则以服务器时间为准
                if ($b['exception_time']) {
                    $return_time = $b['exception_time'];
                } else {
                    $return_time = time();
                }
                // 如果归还时间小于借出时间
                if ($return_time <= $orderInfo['borrow_time']) {
                    $return_time = $orderInfo['borrow_time'];
                }
                // 归还雨伞
                returnBackUmbrella($b['id'], $b['station_id'], $b['slot'], $return_time, true);
                LOG::DEBUG('exception umbrella: ' . $b['id'] . ' orderid: ' . $b['order_id'] . ' umbrella status: ' . $b['status']);
            } else {
                LOG::INFO("order info, " . print_r($orderInfo, 1));
                // 非借出状态, 清除异常记录
                ct('umbrella')->update($b['id'], [
                    'order_id'       => '',
                    'status'         => 0,
                    'exception_time' => 0,
                ]);
                LOG::INFO("clear exception umbrella record, set umbrella: {$b['id']} to normal");
            }
        }
        LOG::DEBUG('handle exception umbrella complete');
        exit;

    # 更新用户信息（支付宝API不支持，只更新微信用户信息）
    case 'update_user_info':
        LOG::DEBUG('update user info starts');
        // 7天内更新过的用户不更新
        $cond['update_time'] = ['glue' => '<', 'value' => date('Y-m-d H:i:s', time() - 7 * 24 * 3600)];
        $userInfo            = ct('user_info')->select('openid')->where($cond)->get();
        $openidList          = array_column($userInfo, 'openid');
        LOG::INFO("need update openid count: " . count($openidList));
        LOG::INFO('openid list: ' . print_r($openidList, 1));
        // 去掉支付宝用户和已取消关注的微信用户
        $userInfo   = ct('user')->select('id, openid')->where([
                'openid'      => $openidList,
                'unsubscribe' => 0,
                'platform'    => PLATFORM_WX,
            ])->get();
        $openidList = array_column($userInfo, 'openid', 'id');
        LOG::INFO("subscribe openid count: " . count($openidList));
        LOG::INFO('openid list2: ' . print_r($openidList, 1));

        $access_token = getAccessToken()['access_token'];

        foreach ($openidList as $key => $value) {
            $info = wxAPI::getUserInfo($access_token, $value);
            if ($info['errcode']) {
                LOG::WARN("uid: $key , openid: $value , update error, " . print_r($info, 1));
                continue;
            }
            $info['nickname'] = json_encode($info['nickname']);
            unset($info['subscribe']);
            unset($info['openid']);
            unset($info['tagid_list']);
            $info['update_time'] = date("Y-m-d H:i:s", time());
            ct('user_info')->update($key, $info);
        }
        LOG::DEBUG('update user info ends');
        break;

    # 芝麻订单 每分钟执行一次
    case 'zhima':
        $bizStatus = [
            ZHIMA_ORDER_COMPLETE_WAIT, //后台手动退全款，后台手动退部分款，雨伞归还时芝麻信用接口响应失败
            ZHIMA_ORDER_QUERY_WAIT, //芝麻订单回调，雨伞归还
            ZHIMA_ORDER_CANCEL_WAIT //支付超时定时任务，后台手动撤销，状态同步借出失败
        ];
        $zmOrders  = ct('trade_zhima')->select('orderid, zhima_order, status')->where(['status' => $bizStatus])->get();
        foreach ($zmOrders as $zmOrder) {
            $orderid = $zmOrder['orderid'];
            LOG::DEBUG('zhima orderid: ' . $orderid . ', status: ' . $zmOrder['status']);
            switch ($zmOrder['status']) {

                # 确认订单完成状态
                case ZHIMA_ORDER_COMPLETE_WAIT:
                    // 调用结算接口, 成功后更新为 查询状态
                    $order = ct('tradelog')->fetch($orderid);
                    // 未结束的订单不能扣款
                    if (in_array($order['status'], [ORDER_STATUS_RENT_CONFIRM, ORDER_STATUS_RENT_CONFIRM_FIRST])) {
                        continue 2;
                    }
                    $returnStation = $order['return_station_name'] ?: '街借伞网点';
                    $params        = [
                        'order_no'          => $zmOrder['zhima_order'],
                        'product_code'      => 'w1010100000000002858',
                        'restore_time'      => date('Y-m-d H:i:s', $order['return_time'] ?: time()),
                        'pay_amount_type'   => 'RENT',
                        'pay_amount'        => $order['usefee'],
                        'restore_shop_name' => $returnStation,
                    ];
                    $resp          = AlipayAPI::zhimaOrderRentComplete($params);
                    LOG::DEBUG('complete result: ' . print_r($resp, true));
                    if (!empty($resp->code) && $resp->code == 10000) {
                        LOG::DEBUG('zhima order complete success, orderid: ' . $orderid);
                        ct('trade_zhima')->update($orderid, [
                                'status'               => ZHIMA_ORDER_QUERY_WAIT,
                                'alipay_fund_order_no' => $resp->alipay_fund_order_no,
                                'update_time'          => time(),
                            ]);
                    } elseif (!empty($resp->code) && $resp->code == 40004 && $resp->sub_code == 'UNITRADE_WITHHOLDING_PAY_FAILED') {
                        LOG::ERROR('zhima order UNITRADE_WITHHOLDING_PAY_FAILED, orderid: ' . $orderid);
                        ct('trade_zhima')->update($orderid, [
                                'status'      => ZHIMA_ORDER_PAY_FAIL_QUERY_RETRY,
                                'update_time' => time(),
                            ]);
                    } elseif (!empty($resp->code) && $resp->code == 40004 && $resp->sub_code == 'ORDER_GOODS_IS_RESTORED') {
                        LOG::ERROR('zhima order ORDER_GOODS_IS_RESTORED, orderid: ' . $orderid);
                        ct('trade_zhima')->update($orderid, [
                                'status'      => ZHIMA_ORDER_QUERY_WAIT,
                                'update_time' => time(),
                            ]);
                    } else {
                        LOG::ERROR('zhima order complete fail, orderid: ' . $orderid);
                    }

                    // 记录用户流水
                    $update_id = ct('wallet_statement')->where(['related_id' => $orderid])->first()['id'];
                    $amount    = ct('tradelog')->fetch($orderid)['usefee'];
                    if ($update_id) {
                        ct('wallet_statement')->update($update_id, [
                            'type'   => WALLET_TYPE_ZHIMA_PAID_UNCONFIRMED,
                            'amount' => $amount,
                            'time'   => date('Y-m-d H:i:s'),
                        ]);
                    } else {
                        $uid = ct('tradelog')->fetch($orderid)['uid'];
                        ct('wallet_statement')->insert([
                            'uid'        => $uid,
                            'related_id' => $orderid,
                            'type'       => WALLET_TYPE_ZHIMA_PAID_UNCONFIRMED,
                            'amount'     => $amount,
                            'time'       => date('Y-m-d H:i:s'),
                        ]);
                    }
                    break;

                # 确认订单扣款完成状态
                case ZHIMA_ORDER_QUERY_WAIT:
                    // 调用查询接口, 如果扣款成功, 则更新成 结算成功状态, 如果扣款失败, 下个周期继续查询
                    $params = [
                        'out_order_no' => $orderid,
                        'product_code' => 'w1010100000000002858',
                    ];
                    $resp   = AlipayAPI::zhimaOrderRentQuery($params);
                    LOG::DEBUG('query result: ' . print_r($resp, true));
                    if (!empty($resp->code) && $resp->code == 10000 && !empty($resp->pay_status) && $resp->pay_status == 'PAY_SUCCESS') {
                        LOG::DEBUG('zhima order query success, orderid: ' . $orderid);
                        ct('trade_zhima')->update($orderid, [
                                'status'               => ZHIMA_ORDER_COMPLETE_SUCCESS,
                                'pay_amount_type'      => $resp->pay_amount_type,
                                'pay_amount'           => $resp->pay_amount,
                                'pay_time'             => $resp->pay_time,
                                'alipay_fund_order_no' => $resp->alipay_fund_order_no,
                                'admit_state'          => $resp->admit_state == 'Y' ? 1 : 0,
                                'openid'               => $resp->user_id,
                                'update_time'          => time(),
                            ]);
                        // 记录用户流水
                        $update_id = ct('wallet_statement')->where(['related_id' => $orderid])->first()['id'];
                        if ($update_id) {
                            ct('wallet_statement')->update($update_id, [
                                'type' => WALLET_TYPE_ZHIMA_PAID,
                                'time' => date('Y-m-d H:i:s'),
                            ]);
                        } else {
                            $uid = ct('user')->where(['openid' => $resp->user_id])['id'];
                            ct('wallet_statement')->insert([
                                'uid'        => $uid,
                                'related_id' => $orderid,
                                'type'       => WALLET_TYPE_ZHIMA_PAID,
                                'amount'     => $resp->pay_amount,
                                'time'       => date('Y-m-d H:i:s'),
                            ]);
                        }

                        // 如果这个订单是用户支付赔偿金的, 即用户没有归还, 只是在支付宝上进行了赔偿, 需更新订单状态
                        if ($resp->pay_amount_type == 'DAMAGE') {
                            if (ct('tradelog')->update($orderid, [
                                'status'      => ORDER_STATUS_TIMEOUT_NOT_RETURN,
                                'usefee'      => $resp->pay_amount,
                                'return_time' => time(),
                                'lastupdate'  => time(),
                            ])) {
                                LOG::DEBUG('damage, update order success ' . $orderid);
                            } else {
                                LOG::ERROR('damage, update order fail ' . $orderid);
                            }
                        }
                        LOG::DEBUG('zhima order finish success, orderid: ' . $orderid);
                    } elseif (!empty($resp->code) && $resp->code == 10000 && !empty($resp->pay_status) && $resp->pay_status == 'PAY_FAILED') {
                        LOG::ERROR('zhima order pay fail, orderid: ' . $orderid);
                        ct('trade_zhima')->update($orderid, [
                                'status'      => ZHIMA_ORDER_PAY_FAIL_QUERY_RETRY,
                                'update_time' => time(),
                            ]);
                    } else {
                        LOG::ERROR('zhima order finish fail, orderid: ' . $orderid);
                    }
                    break;

                # 确认订单取消状态
                case ZHIMA_ORDER_CANCEL_WAIT:
                    // 调用撤销接口, 需增加管理员手动撤销入口
                    $params = [
                        'order_no'     => $zmOrder['zhima_order'],
                        'product_code' => 'w1010100000000002858',
                    ];
                    $resp   = AlipayAPI::zhimaOrderRentCancel($params);
                    LOG::DEBUG('cancel result: ' . print_r($resp, true));
                    if (!empty($resp->code) && $resp->code == 10000) {
                        LOG::DEBUG('zhima order cancel success, orderid: ' . $orderid);
                        ct('trade_zhima')->update($orderid, [
                            'status'      => ZHIMA_ORDER_CANCEL_SUCCESS,
                            'update_time' => time(),
                        ]);
                    } elseif ($resp->code == 40004 && strtoupper($resp->sub_code) == 'ORDER_IS_CANCEL') {
                        // 已经撤销的订单更新芝麻订单状态为已撤销
                        LOG::INFO("order id: $orderid has been cancel in zhima");
                        ct('trade_zhima')->update($orderid, [
                            'status'      => ZHIMA_ORDER_CANCEL_SUCCESS,
                            'update_time' => time(),
                        ]);
                    } else {
                        LOG::ERROR('zhima order cancel fail, orderid: ' . $orderid);
                    }
                    break;
                default:
                    LOG::ERROR('error status');
                    break;
            }
        }
        break;

    # 芝麻订单扣款失败查询 每12小时执行一次（独立出来便于控制请求次数）
    case 'zhima_pay_fail_retry_query':
        $zmOrders = ct('trade_zhima')->select('orderid, zhima_order, status')->where(['status' => ZHIMA_ORDER_PAY_FAIL_QUERY_RETRY])->get();
        foreach ($zmOrders as $zmOrder) {
            // 调用结算接口, 成功后更新为 查询状态
            $orderid       = $zmOrder['orderid'];
            $order         = ct('tradelog')->fetch($orderid);
            $returnStation = $order['return_station_name'] ?: DEFAULT_STATION_NAME;
            $params        = [
                'order_no'          => $zmOrder['zhima_order'],
                'product_code'      => 'w1010100000000002858',
                'restore_time'      => date('Y-m-d H:i:s', $order['return_time'] ?: time()),
                'pay_amount_type'   => 'RENT',
                'pay_amount'        => $order['usefee'],
                'restore_shop_name' => $returnStation,
            ];
            $resp          = AlipayAPI::zhimaOrderRentComplete($params);
            LOG::DEBUG('complete result: ' . print_r($resp, true));
            if (!empty($resp->code) && $resp->code == 10000) {
                LOG::DEBUG('zhima order complete success, orderid: ' . $orderid);
                ct('trade_zhima')->update($orderid, [
                        'status'               => ZHIMA_ORDER_QUERY_WAIT, //丢到上面的定时任务去
                        'alipay_fund_order_no' => $resp->alipay_fund_order_no,
                        'update_time'          => time(),
                    ]);
            } elseif (!empty($resp->code) && $resp->code == 40004 && $resp->sub_code == 'UNITRADE_WITHHOLDING_PAY_FAILED') {
                LOG::ERROR('zhima order UNITRADE_WITHHOLDING_PAY_FAILED, orderid: ' . $orderid);
                ct('trade_zhima')->update($orderid, [
                        'status'      => ZHIMA_ORDER_PAY_FAIL_QUERY_RETRY,
                        'update_time' => time(),
                    ]);
                // 更新用户流水
                $update_id = ct('wallet_statement')->where(['related_id' => $orderid])->first()['id'];
                ct('wallet_statement')->update($update_id, ['time' => date('Y-m-d H:i:s')]);
            } elseif (!empty($resp->code) && $resp->code == 40004 && $resp->sub_code == 'ORDER_GOODS_IS_RESTORED') {
                LOG::ERROR('zhima order ORDER_GOODS_IS_RESTORED, orderid: ' . $orderid);
                ct('trade_zhima')->update($orderid, [
                        'status'      => ZHIMA_ORDER_QUERY_WAIT, // 已完成的订单丢到上面的定时任务去
                        'update_time' => time(),
                    ]);
            } else {
                LOG::ERROR('zhima order complete fail, orderid: ' . $orderid);
            }
        }
        break;

    # 借出未拿走中间态确认 超时确认未拿走 每分钟执行一次
    case 'rent_not_fetch_intermediate_confirm':
        $timeout             = 60; //60s
        $where['status']     = ORDER_STATUS_RENT_NOT_FETCH_INTERMEDIATE;
        $where['lastupdate'] = ['value' => time() - $timeout, 'glue' => '<'];
        $where['tag']        = TAG_UMBRELLA;
        $paidOrders          = ct('tradelog')->where($where)->get();
        // 更新退款
        foreach ($paidOrders as $order) {
            // 雨伞退款到账户余额
            // 更新订单状态, 借还均在原地
            // 非芝麻订单 退全款记录在message中
            $message = $order['message'] ? unserialize($order['message']) : '';
            if ($order['platform'] != PLATFORM_ZHIMA) {
                $message['refund_fee'] = $order['price'];
            }
            $ret = ct('tradelog')->update($order['orderid'], [
                'message'                => $message ? serialize($message) : '',
                'return_station'         => $order['borrow_station'],
                'return_station_name'    => $order['borrow_station_name'],
                'return_shop_id'         => $order['borrow_shop_id'],
                'return_shop_station_id' => $order['borrow_shop_station_id'],
                'return_city'            => $order['borrow_city'],
                'return_device_ver'      => $order['borrow_device_ver'],
                'return_time'            => time(),
                'lastupdate'             => time(),
                'usefee'                 => 0,
                'status'                 => ORDER_STATUS_RENT_NOT_FETCH,
            ]);
            if ($ret) {
                LOG::DEBUG('update order list success, orderid . ' . $order['orderid']);
                // 退还押金到账户余额
                if ($order['platform'] != PLATFORM_ZHIMA) {
                    if (ct('user')->returnBack($order['uid'], $order['price'], $order['price'])) {
                        LOG::DEBUG('success to return money to user account');
                    } else {
                        LOG::ERROR('fail to return money to user account');
                        break; //当前订单更新失败，跳到下一个订单执行
                    }
                    $deposit = $order['price'];
                } else {
                    // 待撤销, 定时任务撤销该订单
                    ct('trade_zhima')->update($order['orderid'], [
                        'status'      => ZHIMA_ORDER_CANCEL_WAIT,
                        'update_time' => time(),
                    ]);
                    LOG::DEBUG('update zhima order waitting for cancel, orderid: ' . $order['orderid']);
                    $deposit = 0;
                }
                // 推送消息: 租借失败通知
                $msg = [
                    'openid'              => $order['openid'],
                    'borrow_station_name' => $order['borrow_station_name'],
                    'borrow_time'         => $order['borrow_time'],
                ];
                addMsgToQueue($order['platform'], TEMPLATE_TYPE_BORROW_FAIL, $msg);
            } else {
                LOG::ERROR('update  order list fail, orderid: ' . $order['orderid']);
            }
        }
        break;

    # 更新超时未归还的订单 每12小时执行一次
    case 'update_borrow_timeout_order':
        LOG::DEBUG("update begin");
        $orders = ct('tradelog')->select('orderid, borrow_time, price, uid, platform, borrow_station, message')->where([
                'status' => [
                    ORDER_STATUS_RENT_CONFIRM,
                    ORDER_STATUS_RENT_CONFIRM_FIRST,
                ],
            ])->get();
        LOG::INFO("total count: " . count($orders));
        $returnTime = time();
        foreach ($orders as $v) {
            if ($v['borrow_time'] == 0) {
                LOG::ERROR("abnormal order: " . print_r($v, 1));
                continue;
            }
            $usefee = calcFee($v['orderid'], $v['borrow_time'], $returnTime);
            // 订单费用大于订单价格时
            if ($usefee >= $v['price']) {
                // 租金扣完，更新订单状态
                // $user['usefee'] = $user['deposit'] = 0;
                $orderMsg               = unserialize($v['message']);
                $orderMsg['refund_fee'] = 0;
                DB::query('begin');
                $tradelogResult = ct('tradelog')->update($v['orderid'], [
                    'status'      => ORDER_STATUS_TIMEOUT_NOT_RETURN,
                    'usefee'      => $v['price'],
                    'message'     => serialize($orderMsg),
                    'return_time' => time(),
                    'lastupdate'  => time(),
                ]);
                // 如果是芝麻信用订单, 则直接更新芝麻信用订单状态, 调用芝麻信用接口结算订单
                if ($v['platform'] != PLATFORM_ZHIMA) {
                    $otherResult = ct('user')->reduceDeposit($v['uid'], $v['price']);
                } else {
                    LOG::DEBUG('zhima order');
                    // 记录用户流水
                    $walletResult = ct('wallet_statement')->insert([
                        'uid'        => $v['uid'],
                        'related_id' => $v['orderid'],
                        'type'       => WALLET_TYPE_ZHIMA_PAID_UNCONFIRMED,
                        'amount'     => $v['price'],
                        'time'       => date('Y-m-d H:i:s'),
                    ]);
                    $otherResult  = ct('trade_zhima')->update($v['orderid'], [
                        'status'      => ZHIMA_ORDER_COMPLETE_WAIT,
                        'update_time' => time(),
                    ]);
                }
                if ($tradelogResult && $otherResult) {
                    LOG::INFO("update orderid: {$v['orderid']} success");
                    DB::query('commit');
                } else {
                    LOG::ERROR("update orderid: {$v['orderid']} fail, tradelogResult: $tradelogResult , otherResult: $otherResult");
                    DB::query('rollback');
                }
            }
        }
        LOG::DEBUG("update end");
        break;

    # 芝麻实体数据上传(只传有商铺的网点) 每2小时一次
    case 'zhima_borrow_entity_upload':
        $allShops = ct('shop')->get();

        foreach ($allShops as $v) {
            $shopStation = ct('shop_station')->select('id, station_id, status, longitude, latitude, fee_settings')->where(['shopid' => $v['id']])->first();
            if (empty($shopStation) || empty($shopStation['station_id'])) {
                continue;
            }
            $coordinates = lbsAPI::aMapCoordinateConvert($shopStation['longitude'] . ',' . $shopStation['latitude']);
            if ($coordinates['status'] != 1) {
                LOG::WARN("amap coordinate convert fail, " . print_r($coordinates, 1));
                continue;
            }
            list($lng, $lat) = explode(',', $coordinates['locations']);
            $station = ct('station')->fetch($shopStation['station_id']);
            $feeStr  = makeFeeStrForZhima(ct('fee_strategy')->getStrategySettings($shopStation['fee_settings']));
            if ($v['province'] == $v['city']) {
                $v['province'] = '';
            }
            if ($shopStation['status'] == 0) {
                $isCanBorrow = 'N';
            } else {
                if ($station['usable']) {
                    $isCanBorrow = 'Y';
                } else {
                    $isCanBorrow = 'N';
                }
            }
            $biz  = [
                'product_code'     => 'w1010100000000002858',
                'category_code'    => 'umbrella',
                'entity_code'      => $shopStation['id'],
                //站点/商铺
                'entity_name'      => '[JJ伞]' . $v['name'],
                'address_desc'     => $v['province'] . $v['city'] . $v['area'] . $v['locate'],
                'longitude'        => $lng,
                'latitude'         => $lat,
                'rent_desc'        => $feeStr,
                // 租金描述
                'collect_rent'     => 'Y',
                'can_borrow'       => $isCanBorrow,
                // 可借判断 可接数量大于0
                'can_borrow_cnt'   => $station['usable'],
                // 可借数量
                'total_borrow_cnt' => $station['total'] > $station['usable'] ? $station['total'] : $station['usable'],
                // 借用总数
                'upload_time'      => date('Y-m-d H:i:s'),
            ];
            $resp = AlipayAPI::zhimaBorrowEntityUpload($biz);
            if ($resp->code == '10000') {
                LOG::INFO("upload success , shop id: {$shopStation['id']} , usable: {$station['usable']} , total: {$station['total']} , $lng,$lat");
            } else {
                LOG::WARN("shop id upload fail" . print_r($resp, 1));
            }
        }
        break;

    # 定期更新微信小程序access token
    case 'updateWeappAccessToken':
        $rst = weAPI::updateAccessToken();
        LOG::INFO('new weapp access token: ' . substr($rst, 0, 10) . '...');
        break;

    default:
        echo 'no crontab';
}