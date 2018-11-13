<?php
use model\Api;
use model\User;

require_once 'common.func.inc.php';
require_once 'lib/swapi.class.php';

/**
 * 向微信申请带场景的临时二维码

 * @param int $sceneId 场景ID
 * @param int $expireTime 过期时间
 * @return boolean|array|string 若请求失败,则返回false, 否则返回二维码数据
 */
function getQrcodeFromWeiXin($sceneId, $expireTime) {
	// 由于轮询一遍单个座位号下单序号(8192)可能会超过7天,临时二维码过期,ticket也过期
	// 所以每次都需重新申请ticket及二维码
	$ac = getAccessToken();
	if(!$ac['access_token'])
		return $ac; // errcode
	$ticket = wxAPI::createQrcode($ac['access_token'], $sceneId, NULL, $expireTime); //临时二维码(有效期 7天)
	if(!$ticket['ticket']) {
		LOG::ERROR("Fail to get qrcode ticket from weixin, sceneId:" . $sceneId . ', access token:' . $ac . ', $ret:' . $ticket . ', exptime:' . $expireTime);
		return $ticket; // errcode
	}
	$imgData = wxAPI::getQrcode($ticket['ticket']);
	if( empty($imgData) ) {
		return makeErrorData(ERR_REQUEST_FAIL, "fail to get qrcode from weixin server, ticket: " . $ticket['ticket']);
	}
	return $imgData;
}

function getTestQrcode($sceneId) {
	$qrcodeData = callWeiXinFunc('getQrcodeFromWeiXin', array($sceneId));
	//若有错误信息, 则返回失败提示信息
	if( isset($qrcodeData['errcode']) ) {
		return $qrcodeData; // errcode
	}
	// 保存二维码图片到本地
	$fileName = DATA_DIR."/qrcode-jjsan-test-". $sceneId .".jpg";
	if( !file_put_contents($fileName, $qrcodeData) ) {
		// 保存失败, 需检查存储的文件夹权限
		LOG::ERROR('fail to store qrcode image to local disk, please check the folder permission, path: ' . $fileName);
		return makeErrorData(ERR_REQUEST_FAIL, 'server error, please try again');
	}

	return ROOT.$fileName;
}

function getQrcodeUrl($sceneId) {
	$ac = getAccessToken();
    if(!$ac['access_token']){
        LOG::WARN("get wechat access token fail, " . print_r($ac, 1));
        return $ac; // errcode
    }
	$ticket = wxAPI::createQrcode($ac['access_token'], $sceneId, NULL); //临时二维码(有效期 7天)
	if(!$ticket['ticket']) {
		LOG::ERROR("Fail to get qrcode ticket from weixin, sceneId:" . $sceneId . ', access token:' . print_r($ac, 1) . ', $ret:' . print_r($ticket, 1));
		return $ticket; // errcode
	}
	return "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=" . $ticket['ticket'];
}

function getLimitQrcodeUrl($sceneId, $limit = 60) {
	$ac = getAccessToken();
	if(!$ac['access_token']){
        LOG::WARN("get wechat access token fail, " . print_r($ac, 1));
        return $ac; // errcode
    }
	$ticket = wxAPI::createQrcode($ac['access_token'], $sceneId, NULL, $limit); //临时二维码(有效期 7天)
	if(!$ticket['ticket']) {
		LOG::ERROR("Fail to get qrcode ticket from weixin, sceneId:" . $sceneId . ', access token:' . print_r($ac, 1) . ', $ret:' . print_r($ticket, 1));
		return $ticket; // errcode
	}
	return "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=" . $ticket['ticket'];
}

function getQrcodeUrlUnLimit($sceneId) {
    // 获取永久二维码
    $ac = getAccessToken();
    if(!$ac['access_token'])
        return $ac; // errcode
    $ticket = wxAPI::createQrcode($ac['access_token'], $sceneId);
    if(!$ticket['ticket']) {
        LOG::ERROR("Fail to get qrcode ticket from weixin, sceneId:" . $sceneId . ', access token:' . print_r($ac, 1) . ', $ret:' . print_r($ticket, 1));
        return $ticket; // errcode
    }
    return "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=" . $ticket['ticket'];
}

function getAccessToken($forceUpdate = false, $clientForce = false, $clientAccessToken = false) {
	LOG::DEBUG('getAccessToken, param: ' . json_encode(func_get_args()));

	$ac = unserialize( C::t('common_setting')->fetch('jjsan_wxaccesstoken') );
	// 若终端请求强制更新, 由于终端检测到accesstoken(ac)过期才进行force操作,
	// 此时终端的ac与云端相同, 则代表云端的ac也过期了,需要云端向微信发送请求更新ac
	// 若此时终端的ac与云端不同, 则可能云端的ac已经被其他终端force update过了,直接返回云端的ac即可
	if($clientForce) {
		LOG::DEBUG('client request to force update');
		if($clientAccessToken && $ac['access_token'] && $clientAccessToken == $ac['access_token']) {
			$forceUpdate = true;
			LOG::DEBUG('cloud decide to force update');
		}
	}

	if ($forceUpdate || !$ac || !$ac['access_token'] || time() - $ac['timestamp'] > $ac['expires_in'] ) {
		// 以下采用flock文件锁机制对 从微信请求更新ac 这一个操作进行加锁,保证同一时间只有一个请求(进程)可以更新ac
		// 避免并发更新带来的数据不一致,以及ac失效的问题
		// 注意: 保证目录的写权限(安装时需新建文件夹)
		$lockFile = DATA_DIR . "/locks/wx_access_token_lock";
		$fp = fopen($lockFile, "w+");
		if($fp === false) {
			LOG::DEBUG('can not access lock file: ' . $lockFile);
			return makeErrorData(ERR_FILE_OPT_FAIL, 'can not access lock file: ' . $lockFile);
		}

		$lockStatus = flock($fp, LOCK_EX | LOCK_NB); // 加锁, 独占非阻塞, 直接返回

		if($lockStatus) {
			// 获得锁, 则可向微信直接发送请求
			$ac = updateAccessTokenLocked();
			LOG::DEBUG('......update access token to db');
			flock($fp, LOCK_UN); // 释放锁
			fclose($fp);
		} else {
			fclose($fp); // 先关闭前面的写文件
			$fp = fopen($lockFile, "r");
			if($fp === false) {
				LOG::DEBUG('can not access lock file: ' . $lockFile);
				return makeErrorData(ERR_FILE_OPT_FAIL, 'can not access lock file: ' . $lockFile);
			}
			// 共享锁,需等待独占锁释放才能执行, 阻塞,等待写结束后再读
			flock($fp, LOCK_SH);
			flock($fp, LOCK_UN); //释放锁
			fclose($fp);
			//从数据库中读数据
			LOG::DEBUG('......access token read from db');
			$ac = unserialize( C::t('common_setting')->fetch('jjsan_wxaccesstoken') );
			if(! $ac['access_token'])
				return $ac; // return errcode
		}
	}

	if(! $ac['access_token']) {
		LOG::ERROR('can not get access_token, ret:', print_r($ret, true));
		return $ac; // return errcode
	}
	LOG::DEBUG('get access token ok');
	return array('access_token' => $ac['access_token'],
			'expires_in' => $ac['expires_in'] + $ac['timestamp'] - time()
	);
}

function updateAccessTokenLocked() {
	$ac = wxAPI::updateAccessToken();
	if ( $ac['access_token'] ) {
		// add custom update cycle for weixin
		if(defined('WX_ACCESSTOKEN_UPDATE_CYCLE') && WX_ACCESSTOKEN_UPDATE_CYCLE < $ac['expires_in'] ) {
			$ac['expires_in'] = WX_ACCESSTOKEN_UPDATE_CYCLE;
		}
		if(! C::t('common_setting')->update('jjsan_wxaccesstoken', serialize($ac))) {
			LOG::ERROR("fail to update jjsan_wxaccesstoken to db");
			return makeErrorData(ERR_SERVER_DB_FAIL, "fail to update jjsan_wxaccesstoken to db");
		}

	} else {
		// 将错误码也写入数据库中
		C::t('common_setting')->update('jjsan_wxaccesstoken', serialize($ac));
	}
	return $ac;
}

/**
 *
 * @param string $force JsApiToken 与 AccessToken 没有耦合关系
 * JsApiToken微信默认只有两个小时有效期, 不同的有效AccessToken请求获得的JsApiToken在同一个JsApiToken有效期内是相同的
 * @return mixed 返回 JsApiToken 或 错误返回码
 */
function getJsApiTicket($force = false) {
	$jt = unserialize( C::t('common_setting')->fetch('jjsan_wxjsapiticket') );
	if ($force || !$jt || time() - $jt['timestamp'] > $jt['expires_in'] ) {
		$ac = getAccessToken();
		if(! $ac['access_token']) {
			LOG::DEBUG('getJsApiTicket:can not get access token');
			return $ac; // return errcode
		}
		LOG::DEBUG('start to udpate jsapiticket');
		$jt = wxAPI::updateJsApiTicket($ac['access_token']);

		if ( $jt['ticket'] ) {
			// add custom update cycle for weixin
			if(defined('WX_JSAPITICKET_UPDATE_CYCLE') && WX_JSAPITICKET_UPDATE_CYCLE < $jt['expires_in'] ) {
				$jt['expires_in'] = WX_JSAPITICKET_UPDATE_CYCLE;
			}
			if(! C::t('common_setting')->update('jjsan_wxjsapiticket', serialize($jt))) {
				LOG::ERROR("fail to update jjsan_wxjsapiticket to db");
				return makeErrorData(ERR_SERVER_DB_FAIL, "fail to update jjsan_wxjsapiticket to db");
			}
		} else {
			LOG::ERROR("fail to update wxjsapiticket from WeiXin, force:" . $force);
			return $jt; // return error code and msg
		}
	}

	return array('ticket' => $jt['ticket'],
			'expires_in' => $jt['expires_in'] + $jt['timestamp'] - time()
	);
}

function getJsApiTicketValue($force = false) {
	$ret = callWeiXinFunc('getJsApiTicket', array($force));
	if($ret['ticket'])
		return $ret['ticket'];
	return false;
}

function getImagesFromWeiXin($offset, $count) {
	LOG::DEBUG("getImagesFromWeiXin, offset: " . $offset . ", count: " . $count);
	$ac = getAccessToken();
	if(! $ac['access_token']) {
		return $ac; // return errcode
	}
	$ret = wxAPI::getMaterial($ac['access_token'], 'image', $offset, $count);
	if(! isset($ret['item_count'])) {
		return $ret; // return errcode
	}

	return $ret;
}

function getLatestOrderByBorrowStationId($sid) {
	return ct('tradelog')->getLatestOrderByBorrowStationId($sid);
}

function getOrderStatus($orderId) {
	return ct('tradelog')->getOrderStatus($orderId);
}

function getNicnameByOpenid( $openid ) {
	$ac = getAccessToken();
	$userinfo = wxAPI::getUserInfo($ac['access_token'], $openid );
	LOG::DEBUG('userinfo:' . print_r($userinfo['nickname'], true));
	if($userinfo['errcode'])
		return $userInfo;
	return $userinfo['nickname'];
}

function getNicnameByOpenidBatch( $openids ) {
	$ac = getAccessToken();
	$userInfoList = wxAPI::getUserInfoBatch($ac['access_token'], $openids );
	LOG::DEBUG('userinfolist:' . print_r($userInfoList, true));
	if($userinfo['errcode'])
		return $userInfo;
	$userInfoList = $userInfoList['user_info_list'];
	foreach ($userInfoList as $userInfo) {
		$ret[] = $userInfo['nickname'];
	}
	return $ret;
}

function addInstallMan($openid, $uid) {
	$platform = ct('user')->getPlatformFromUid($uid);
	if ($platform == PLATFORM_WX) {
		$nickname = callWeiXinFunc('getNicnameByOpenid', array($openid));
	} elseif ($platform == PLATFORM_ALIPAY) {
		// wait to add : alipay get nickname
	}

	$installs = json_decode( C::t('common_setting')->fetch('jjsan_install_man'), true );
	$installs_verifying = json_decode( C::t('common_setting')->fetch('jjsan_install_man_verifying'), true );
	$installs_user = json_decode( C::t('common_setting')->fetch('jjsan_install_man_user'), true );
	if($installs["$uid"] || $installs_user["$uid"] || $installs_verifying["$uid"])
		return true;

	$installs_verifying["$uid"] = "$nickname";
	if(! C::t('common_setting')->update('jjsan_install_man_verifying', json_encode($installs_verifying))) {
		LOG::ERROR("fail to addInstallMan verifying to db openid:" . $openid);
		return false;
	}
	return true;
}

function getOpenIDFromWeiXin($code) {
	$ret = wxAPI::getAuthorizedAccessTokenInPage($code);
	if(! $ret['openid']) {
		LOG::ERROR('getOpenIDFromWeiXin error: ' . print_r($ret, true));
		return false;
	}
	return $ret['openid'];
}

//========================= 站点模块 =============================//
/**
 * 添加站点
 */
function addStation($mac, $channelid) {
	$ret = ct('station')->fetch_by_field('mac', $mac);
	if(! $ret) {
		// 先查询是否mac是否已经存在, 存在则禁止添加
		// $newItem = array('mac' => $mac, 'channelid' => $channelid, 'lbsid' => 0, 'total' => 0, 'usable' => 0);
		$newItem = array('mac' => $mac, 'channelid' => $channelid, 'total' => 0, 'usable' => 0);
		$ret = ct('station')->insert($newItem, true);
		if($ret <= 0) {
			LOG::ERROR('station db insert error');
			echo json_encode(makeErrorData(ERR_SERVER_DB_FAIL, 'db error'));
			exit;
		}
		LOG::DEBUG('station db insert successfully, mac:' . $mac . 'channelid:' . $channelid);
		$newItem['id'] = $ret;
		$ret = $newItem;
	}
	return $ret;
}

/*
  更新LBS数据
  $shop_station_id lbs上绑定的索引key, 对应的商铺站点ID
*/
function updateStationToLBS($lbsid, $item) {
	//更新LBS云数据
	$item['id'] = $lbsid; //百度LBS默认索引key
	LOG::DEBUG('lbs item:' . json_encode($item));
	require_once JJSAN_DIR_PATH . 'lib/lbsapi.class.php';
	$ret = lbsAPI::updatePOI($item);
 	if($ret['status'] != 0) {
		LOG::ERROR("update station lbs info fail, ret:" . print_r($ret, true));
 	}
	return makeErrorData($ret['status'], $ret['message']);
}

function bindMapPoint($lbsid, $shop_station_id, $enable) {
	LOG::DEBUG("bindMapPoint lbsid:" . $lbsid . ', shop_station_id:' . $shop_station_id . ', enable:' . $enable);
	//更新LBS云数据
	$shop_station_id_for_lbs = $enable? $shop_station_id : 0; // 绑定 或 解绑定
	$item = array('id'=>$lbsid, 'sid'=>$shop_station_id_for_lbs, 'enable'=>$enable);
	require_once __DIR__ . '/lib/lbsapi.class.php';
	$ret = lbsAPI::updatePOI($item);
	if($ret['status'] != 0) {
		LOG::ERROR("update station lbs info fail, ret:" . print_r($ret, true));
		return makeErrorData($ret['status'], $ret['message']);
	} else {
		$lbsidNew = $enable? $lbsid : 0; // 绑定 或 解绑定
		$ret = ct('shop_station')->update($shop_station_id, array('lbsid' => $lbsidNew));
		if(! $ret) {
			LOG::ERROR("bindMapPoint update db fail or not update");
			return makeErrorData(ERR_SERVER_DB_FAIL, 'server db fail');
		}
	}

	return makeErrorData(ERR_NORMAL, 'success');
}

function calcFee($orderid, $rentTime, $returnTime) {
	LOG::DEBUG('orderid: ' . $orderid . ' renttime: ' . $rentTime . ', returntime: ' . $returnTime);

	$feeSettings = json_decode(ct('tradeinfo')->getField($orderid, 'fee_strategy'), true);

	LOG::DEBUG('fee settings:' . json_encode($feeSettings));
	return calcFeeWithFeeSettings($feeSettings, $rentTime, $returnTime);
}

function calcFeeForStation($sid, $rentTime, $returnTime) {
	LOG::DEBUG('calc station fee sid: ' . $sid . ' renttime: ' . $rentTime . ', returntime: ' . $returnTime);
	$feeSettings = ct('shop_station')->getFeeSettings($sid);
	LOG::DEBUG('fee settings:' . json_encode($feeSettings));
	return calcFeeWithFeeSettings($feeSettings, $rentTime, $returnTime);
}

function calcFeeWithFeeSettings($feeSettings, $rentTime, $returnTime) {
	$usetime = $returnTime - $rentTime;
	if ( !empty($feeSettings['free_time']) && $usetime <= $feeSettings['free_time']*$feeSettings['free_unit'] ) {
		LOG::DEBUG('uid return umbrella in free time');
		return 0;
	}

	if ( !empty($feeSettings['max_fee_time']) && !empty($feeSettings['max_fee']) ) {
		return calcFeeNew($feeSettings, $rentTime, $returnTime);
	} else {
		$usefee = ceil(($usetime - ($feeSettings['fixed_time']*$feeSettings['fixed_unit'])) / ($feeSettings['fee_unit'] * $feeSettings['fee_time'])) * $feeSettings['fee'];
		$usefee = ($usefee > 0 ? $usefee : 0) + $feeSettings['fixed'];
		if(!empty($feeSettings['max_fee'])){
		    $usefee = min($usefee, $feeSettings['max_fee']);
        }
	}

	return $usefee;
}

function calcFeeNew($feeSettings, $rentTime, $returnTime)
{
	LOG::DEBUG('calculate fee with max_day_fee');

	LOG::DEBUG('calculate fee feeSetting: '. json_encode($feeSettings));
	$usetime = $returnTime - $rentTime;

	$day = floor($usetime / ( $feeSettings['max_fee_time'] * $feeSettings['max_fee_unit'] ));
    LOG::DEBUG('usetime : ' . $usetime . ' day : ' . $day);
	$remain_time = $usetime % ( $feeSettings['max_fee_time'] * $feeSettings['max_fee_unit'] );
	if ($day > 0) {
		$remain_fee = ceil( ($remain_time) / $feeSettings['fee_unit']) * $feeSettings['fee'];
		$remain_fee = $remain_fee > $feeSettings['max_fee'] ? $feeSettings['max_fee'] : $remain_fee;
		$total_fee = $remain_fee + $day * $feeSettings['max_fee'];
	} else {
		$total_fee = ceil(($usetime - ($feeSettings['fixed_time']*$feeSettings['fixed_unit'])) / ($feeSettings['fee_unit'] * $feeSettings['fee_time'])) * $feeSettings['fee'];
		$total_fee = ($total_fee > 0) ? $total_fee : 0;
		$total_fee += $feeSettings['fixed'];
		$total_fee = $total_fee > $feeSettings['max_fee'] ? $feeSettings['max_fee'] : $total_fee;
	}
	$total_fee = ($total_fee < 0) ?  0 : $total_fee;

	return $total_fee;
}

function refund($uid, $refund) {
	$refund = round($refund, 2); //四舍五入，避免太长精度
	$totalRefund = $refund;
	LOG::DEBUG('refund now, user id: ' . $uid . ', refund: ' . $totalRefund);
	if(empty($uid) || empty($refund) || ! is_numeric($refund) || $refund == 0) {
		//echo json_encode(makeErrorData(ERR_PARAMS_INVALID, 'invalid parameter'));
		LOG::ERROR('invalid parameter');
		return false;
	}

	// 查出所有该openid的order状态为已支付过可用于退款的订单(芝麻信用订单除外)
	$orders = ct('tradelog')->getPayOrdersByStatusAndTag($uid, [ORDER_STATUS_WAIT_PAY], TAG_UMBRELLA);
	$ids = array();
	$platform = ct('user')->getPlatformFromUid($uid);
	$refundDetail = array();

	foreach($orders as $order) {
		$orderid = $order['orderid'];
        $paid = $order['paid'] ? : $order['price'];

        // 这里应该用 $order['price'] 替代 paid, 因为为了使让总退单数最少,
        // 需优先选择refunded最小的, 即已退过最小的, 为了让所有refunded具有可比性,
        // 在微信/支付宝支付成功后, 会将refunded设成账户内扣除的那边分, 剩余的可退空间就是paid
        // 所以退款时判断还剩多少可退, 应该用 order['price'] - refunded
        $refundable = round($order['price']-$order['refunded'], 2);
		$refundFee = $refund > $refundable ? $refundable : $refund;
		LOG::DEBUG('try to refund:' . $refundFee . ', orderid: ' . $orderid);
		// check
		if($refundable <= 0) {
			LOG::DEBUG('update order status to all refunded, orderid:' . $orderid);
			if(! ct('tradelog')->update($orderid, array('refundno' => ORDER_ALL_REFUNDED, 'lastupdate' => time()))) {
				LOG::ERROR('update order refund status error');
			}
			continue;
		}
		// 退款
		$refundFee = round($refundFee, 2); //四舍五入，避免太长精度发生错误

		if ($platform == PLATFORM_WX) {
			$input = new WxPayDataBase();
			$input->values = array(
					'out_trade_no' => $orderid,
					'out_refund_no' => $orderid."-R".$order['refundno'],
					'total_fee' => ($paid * 100),
					'refund_fee' => ($refundFee * 100),
					'op_user_id' => 'JJSAN-WX-REFUND-ROBOT',
			);
			$ret = WxPayApi::refund($input);
			LOG::DEBUG(print_r($ret, true));
			if ( $ret['return_code'] == 'SUCCESS' && $ret['result_code'] == 'SUCCESS' ) {
				$refundResult = true;
				LOG::DEBUG('wxpay refund success');
			} else if($ret['err_code'] == 'NOTENOUGH' || $ret['err_code'] == 'SYSTEMERROR') {
				// 以下策略保证尽可能的少分单退款
				// 若微信支付账户 未结算金额不足,则暂停此次退款,等到账户余额充足再自动退款
				// 若微信返回系统错误, 则等待下一轮退款再重试
				LOG::DEBUG('try again next time');
				break;
			} else if($ret['err_code'] == 'REFUND_FEE_MISMATCH') {
				// 若出现订单金额不一致的问题, 则代表前一次提交的一次退款失败了, 然后紧接着分单尝试退了部分
				// 等到下一轮尝试退款时,这个订单该退的金额和之前不一致了(别的单退了部分)
				// 则这次采用同样的退款编号但金额不同的退款会失败, 需要将退款编号更新一下再次尝试退款
				LOG::DEBUG('REFUND_FEE_MISMATCH, increment refundno, and try again next time');
				ct('tradelog')->update($orderid, array('refundno' => $order['refundno'] + 1, 'lastupdate' => time()));
				break;
			} else {
				$refundResult = false;
			}
		}
		// 支付宝退款
		else if($platform == PLATFORM_ALIPAY){
			require_once __DIR__ . '/lib/alipay/AlipayAPI.php';
			$params = [
				'out_trade_no' => $orderid,
				'out_request_no' => $orderid."-R".$order['refundno'],
				'refund_amount' => $refundFee,
				'operator_id' => 'jjsan-WX-REFUND-ROBOT',
				'refund_reason' => 'JJ伞-租赁押金退款',
			];

			$ret = AlipayAPI::refund($params);
			LOG::DEBUG(print_r($ret, true));
			if($ret->code == 10000) {
				$refundResult = true;
				LOG::DEBUG('alipay refund success');
			} else {
				$refundResult = false;
			}
		}
		// 不支持
		else {
			continue;
		}

		if ($refundResult) {
			LOG::DEBUG('refund success, orderid: ' . $orderid . ', refund:' . $refundFee);
			$order['refunded'] += $refundFee;
			$refundno = $order['price'] == $order['refunded'] ? ORDER_ALL_REFUNDED : ($order['refundno']+1);
			$refundDetail[] = [$orderid, $refundFee]; // 记录退款详情

			$ret = ct('tradelog')->update($orderid, array('refundno' => $refundno, 'refunded'=>$order['refunded'], 'lastupdate' => time()));
			if(! $ret) {
				LOG::ERROR('update order refund no fail');
			}
		} else {
			LOG::ERROR('wxpay refund fail, orderid: ' . $orderid . ', refund:' . $refundFee . ', ret:' . print_r($ret, true));
			LOG::ERROR('continue next order');
			continue;
		}


		$refund -= $refundFee;
		$refund = round($refund, 2);
		LOG::DEBUG('left:' . $refund);
		if($refund <= 0)
			break;
	}

	if($refund > 0) {
		LOG::ERROR('refund no complete, and left:' . $refund);
	} else {
		LOG::DEBUG('refund all success');
	}

	$refund = $totalRefund - $refund;
	$refund = round($refund, 2);

	$ret = true;
	if($refund > 0) {
		$ret = ct('user')->refund($uid, $refund, $platform);
	}
	if($ret) {
		LOG::DEBUG('update user account money success');
	} else {
		LOG::ERROR('update user account money fail');
	}
	return ['refunded' => $refund, 'batch_no' => $batchNo? : '', 'detail' => $refundDetail];
}

function addMsgToQueue($platForm, $type, $msg) {
	try {
		switch ($platForm) {
		    # 微信模板
			case PLATFORM_WX:
			    require_once JJSAN_DIR_PATH . 'lib/wxapi.class.php';

                switch ($type) {

                    case TEMPLATE_TYPE_BORROW_UMBRELLA :
                        $data = getWeChatBorrowUmbrellaMsg($msg);
                        break;

                    case TEMPLATE_TYPE_BORROW_FAIL :
                        $data = getWeChatBorrowFailMsg($msg);
                        break;

                    case TEMPLATE_TYPE_RETURN_UMBRELLA :
                        $data = getWeChatReturnUmbrellaMsg($msg);
                        break;

                    case TEMPLATE_TYPE_WITHDRAW_APPLY :
                        $data = getWeChatWithdrawApplyMsg($msg);
                        break;

                    case TEMPLATE_TYPE_RETURN_REMIND :
                        $data = getWechatReturnRemindMsg($msg);
                        break;

                    case TEMPLATE_TYPE_BROKEN_REMIND :
                        $data = getWechatBrokenRemindMsg($msg);
                        break;

                    case TEMPLATE_TYPE_REFUND_FEE:
                        $data = getWechatRefundFeeMsg($msg);
                        break;

                    case TEMPLATE_TYPE_LOSE_UMBRELLA:
                        $data = getWechatLoseUmbrellaMsg($msg);
                        break;

                    default:
                }

				$ret = callWeiXinFuncV2("wxAPI::sendTemplateMsg", array($data));
				if($ret['errcode'] == 0 && ! empty($ret['msgid'])) {
					$msgid = $ret['msgid'];
				} else {
					LOG::ERROR('send wx template fail: ' . print_r($ret, true));
				}
				break;

            # 支付宝和芝麻信用模板
            case PLATFORM_ALIPAY:
			case PLATFORM_ZHIMA:
				require_once JJSAN_DIR_PATH . 'lib/alipay/AlipayAPI.php';

                switch ($type) {

                    case TEMPLATE_TYPE_BORROW_UMBRELLA :
                        $data = getAlipayBorrowUmbrellaMsg($msg);
                        break;

                    case TEMPLATE_TYPE_BORROW_FAIL :
                        $data = getAlipayBorrowFailMsg($msg);
                        break;

                    case TEMPLATE_TYPE_RETURN_UMBRELLA :
                        $data = getAlipayReturnUmbrellaMsg($msg);
                        break;

                    case TEMPLATE_TYPE_WITHDRAW_APPLY :
                        $data = getAlipayWithdrawApplyMsg($msg);
                        break;

                    case TEMPLATE_TYPE_RETURN_REMIND :
                        $data = getAlipayReturnRemindMsg($msg);
                        break;

                    case TEMPLATE_TYPE_REFUND_FEE:
                        $data = getAlipayRefundFeeMsg($msg);
                        break;

                    case TEMPLATE_TYPE_LOSE_UMBRELLA:
                        $data = getAlipayLoseUmbrellaMsg($msg);
                        break;

                    default:
                }

				if(! AlipayAPI::sendTemplateMsg($data)) {
					LOG::ERROR('send alipay template msg error');
				} else {
					$msgid = true;
					LOG::DEBUG('send alipay template msg success');
				}
				break;

            # 微信小程序模板
            case PLATFORM_WEAPP:
                if ($form_id = getFormIdByOpenid($msg['openid'])) {
                    $msg['form_id'] = $form_id;
                } else {
                    LOG::WARN('weapp has not valid form id');
                    // @todo 如果用户关注了公众号，推送公众号模板消息
                    break;
                }
                require_once JJSAN_DIR_PATH . 'lib/weapi.class.php';
                switch ($type) {

                    case TEMPLATE_TYPE_BORROW_UMBRELLA :
                        $data = getWeappBorrowSuccessMsg($msg);
                        break;

                    case TEMPLATE_TYPE_BORROW_FAIL :
                        $data = getWeappBorrowFailMsg($msg);
                        break;

                    case TEMPLATE_TYPE_RETURN_UMBRELLA :
                        $data = getWeappReturnUmbrellaMsg($msg);
                        break;

                    case TEMPLATE_TYPE_WITHDRAW_APPLY :
                        $data = getWeappWithdrawApplyMsg($msg);
                        break;

                    case TEMPLATE_TYPE_RETURN_REMIND :
                        $data = getWeappReturnRemindMsg($msg);
                        break;

                    case TEMPLATE_TYPE_REFUND_FEE:
                        $data = getWeappRefundFeeMsg($msg);
                        break;

                    case TEMPLATE_TYPE_LOSE_UMBRELLA:
                        $data = getWeappLoseUmbrellaMsg($msg);
                        break;

                    default:
                        break 2;
                }
                $ret = weApi::sendWeTemplateMsg($data);
                if($ret['errcode'] == 0) {
                    $msgid = 'weapp';
                } else {
                    LOG::INFO('weapp msg send fail, ' . print_r($ret, 1));
                }
                break;
			default:
				LOG::DEBUG('error msg type: ' . $type);
				break;
		}
	} catch(Exception $e) {
		LOG::ERROR($e->getMessage());
		LOG::ERROR('send msg exception');
	}
	if ($msgid) {
		LOG::DEBUG('send msg success msgid: ' . $msgid);
	} else {
		LOG::ERROR('send msg success fail');
	}
}

function humanTime($seconds) {
	$minutes = floor($seconds / 60);
	$seconds = $seconds - $minutes * 60;
	$hours   = floor($minutes / 60);
	$minutes = $minutes - $hours * 60;
	$days    = floor($hours / 24);
	$hours = $hours - $days * 24;

	$ret = '';
	if ($days > 0) {
		$ret .= $days . '天';
	}
	if($hours > 0) {
		$ret .= $hours . '小时';
	}
	if($minutes > 0) {
		$ret .= $minutes . '分';
	}
	if($seconds > 0) {
		$ret .= $seconds . '秒';
	}
	//特殊
	if($ret == '1分') {
	    $ret = '60秒';
    }

	if(! $ret)
		return '0秒';
	return $ret;
}

function timeUnit($sec) {
	switch($sec) {
		case 1:
			return '秒';
		case 60:
			return '分钟';
		case 3600:
			return '小时';
		case 86400:
			return '天';
		default:
			return humanTime($sec);
	}
}

function getStationCity($station_id = 0) {
	if ($station_id) {
		$ret = ct('station')->fetch($station_id);
		return substr( $ret['address'], 0, strpos($ret['address'] , '市')+3);
	} else {
		$ret = DB::fetch_all( 'SELECT address FROM %t WHERE address <> \'\'', array('jjsan_station') );
		foreach ($ret as $key => $value) {
			$city_list[] = substr($value['address'] , 0, strpos($value['address'] , '市')+3);
		}
		return array_unique($city_list);
	}

}

function getPlatform() {
	if(strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
		return PLATFORM_WX;
	} else if(strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient') !== false) {
		return PLATFORM_ALIPAY;
	}
	return PLATFORM_NO_SUPPORT;
}

function notifyOrderPaid($orderid, $paid) {
	$order = ct('tradelog')->fetch($orderid);
	$orderStatus = $order['status'];
	$uid = $order['uid'];
	switch ($orderStatus) {

        # 待支付,出货&变更订单状态
        case ORDER_STATUS_WAIT_PAY:
            // 借雨伞
            if($order['tag'] == TAG_UMBRELLA) {
                // 若是部分支付, 需扣除账户余额
                $price = round($order['price'], 2);
                $needPayMore = 0;
                // 芝麻信用不需要支付押金, paid均为0
                if ($order['platform'] != PLATFORM_ZHIMA) {
                    // 订单中有部分或者全部是押金支付的情况, refunded指的是已退款的额度
                    if ($paid < $price) {
                        $usableMoney = ct('user')->getField($uid, 'usablemoney');
                        LOG::DEBUG('usable: ' . $usableMoney);
                        if (round($usableMoney + $paid, 2) < $price) {
                            LOG::ERROR('usable not enough, please check the paid: ' . $paid);
                            ct('tradelog')->update($orderid, ['status' => ORDER_STATUS_PAID_NOT_ENOUGH_EXCEPTION, 'lastupdate' => time()]);
                            return false;
                        }
                        $needPayMore = $price - $paid;
                    }
                    LOG::DEBUG('need pay with usable money: ' . $needPayMore);
                    // 更新用户账户 余额和押金 (第二参数是使用余额付款的钱, 第三个参数是增加的押金的钱)
                    $ret = ct('user')->payMore($uid, $needPayMore, $order['price']);
                    if (!$ret) {
                        LOG::ERROR("userneed pay with usable money fail:" . $orderid);
                        return false;
                    }
                    ct('wallet_statement')->insert([
                        'uid' => $uid,
                        'related_id' => $orderid,
                        'type' => WALLET_TYPE_PREPAID,
                        'amount' => $paid,
                        'time' => date('Y-m-d H:i:s'),
                    ]);
                }
                // 更新订单 @todo 保存雨伞图片
                $ret = ct('tradelog')->update($orderid, ['status' => ORDER_STATUS_PAID, 'refunded' => $needPayMore, 'lastupdate' => time(), 'paid' => $paid]);
                if (!$ret) {
                    LOG::ERROR("wxpay notify success, but status update fail. orderid:" . $orderid);
                    return false;
                }

                if ($order['platform'] == PLATFORM_WX) {
                    LOG::DEBUG("wxpay notify");
                } elseif ($order['platform'] == PLATFORM_ALIPAY) {
                    LOG::DEBUG("alipay notify");
                } elseif ($order['platform'] == PLATFORM_ZHIMA) {
                    LOG::DEBUG("zhima notify");
                }
                LOG::DEBUG("orderid $orderid , update status paid");
                // 出伞命令
                swAPI::borrowUmbrella($order['borrow_station'], $orderid);
            }
            break;

        default:
            return false;
    }

	// 记录下用户充值事件
	$u = new model\User();
	$u -> user_top_up($uid,$paid);

	return true;
}

function getWeChatReturnUmbrellaMsg($msg) {
    return [
        "touser" => $msg['openid'],
        "template_id" => WX_TEMPLATE_UMBRELLA_RETURN,
        "url" => "http://" . SERVER_DOMAIN . "/index.php?mod=wechat&act=user&opt=center#/userWallet",
        "data" => [
            "first" =>    ["value" => "你好，你已成功归还一把雨伞！", "color" => "#173177"],
            "keyword1" => ["value" => $msg['return_station_name'], "color" => "#173177"],
            "keyword2" => ["value" => date('Y-m-d H:i:s', $msg['return_time']), "color" => "#173177"],
            "keyword3" => ["value" => humanTime($msg['used_time']), "color" => "#173177"],
            "keyword4" => ["value" => $msg['orderid'], "color" => "#173177"],
            "remark" =>   ["value" => "此次租借产生费用{$msg['price']}，点击详情提取剩余押金。如有疑问，请致电" . CUSTOMER_SERVICE_PHONE . "。", "color" => TEMPLATE_REMARK_FONT_COLOR]
        ]
    ];
}

function getWeChatBorrowUmbrellaMsg($msg) {
    return [
        "touser" => $msg['openid'],
        "template_id" => WX_TEMPLATE_UMBRELLA_BORROW,
        "url" => "http://" . SERVER_DOMAIN . "/index.php?mod=wechat&act=user&opt=center#/userRecord",
        "data" => [
            "first" =>    ["value" => "你好，你已成功租借一把雨伞。", "color" => "#173177"],
            "keyword1" => ["value" => $msg['borrow_station_name'], "color" => "#173177"],
            "keyword2" => ["value" => date('Y-m-d H:i:s', $msg['borrow_time']), "color" => "#173177"],
            "keyword3" => ["value" => $msg['orderid'], "color" => "#173177"],
            "remark" =>   ["value" => "雨伞借出成功，感谢你的使用。", "color" => TEMPLATE_REMARK_FONT_COLOR]
        ]
    ];
}

function getWeChatBorrowFailMsg($msg) {
    return [
        "touser" => $msg['openid'],
        "template_id" => WX_TEMPLATE_BORROW_FAIL,
        "url" => "http://" . SERVER_DOMAIN . "/index.php?mod=wechat&act=user&opt=pay#/oneKeyUse",
        "data" => [
            "first" =>    ["value" => "你好，雨伞租借失败了。", "color" => "#173177"],
            "keyword1" => ["value" => $msg['borrow_station_name'], "color" => "#173177"],
            "keyword2" => ["value" => date('Y-m-d H:i:s', $msg['borrow_time']), "color" => "#173177"],
            "remark" =>   ["value" => "雨伞借出失败，点击详情重新借伞。", "color" => TEMPLATE_REMARK_FONT_COLOR]
        ]
    ];
}

function getWechatReturnRemindMsg($msg)  {
    return [
        "touser" => $msg['openid'],
        "template_id" => WX_TEMPLATE_RETURN_REMIND,
        "url" => "http://" . SERVER_DOMAIN . "/index.php?mod=wechat&act=user&opt=center#/userRecord",
        "data" => [
            "first" => ["value" => "请尽快归还租借的雨伞。", "color" => "#173177"],
            "keyword1" => ["value" => humanTime($msg['difftime']), "color" => "#173177"], //租借时长
            "keyword2" => ["value" => $msg['usefee'] . "元", "color" => "#173177"], //产生费用
            "remark" => ["value" => "你租借的雨伞已经产生了{$msg['usefee']}元的租借费用，点击详情查看租借记录。如有疑问，请致电" . CUSTOMER_SERVICE_PHONE . "。", "color" => TEMPLATE_REMARK_FONT_COLOR]
        ]
    ];
}

function getWechatBrokenRemindMsg($msg)  {
    $first = "你好，有雨伞损坏。";
    $remark = "请尽快进行维护";

    return [
        "touser" => $msg['openid'],
        "template_id" => WX_TEMPLATE_BROKEN_REMIND,
        "data" => [
            "first" => array("value" => $first, "color" => "#173177"),
            "keyword1" => array("value" => $msg['station_id'], "color" => "#173177"), //站点ID
            "keyword2" => array("value" => $msg['address'], "color" => "#173177"), //站点地址
            "keyword3" => array("value" => $msg['slot'], "color" => "#173177"), //损坏雨伞所在槽位号
            "keyword4" => array("value" => $msg['umbrella_id'], "color" => "#173177"), //损坏雨伞ID
            "remark" => array("value" => $remark, "color" => TEMPLATE_REMARK_FONT_COLOR)
        ]
    ];
}

function getWeChatWithdrawApplyMsg($msg) {
    return [
        "touser" => $msg['openid'],
        "template_id" => WX_TEMPLATE_WITHDRAW_APPLY,
        "url" => "http://" . SERVER_DOMAIN . "/index.php?mod=wechat&act=user&opt=center#/userWallet",
        "data" => [
            "first" => ["value" => "你好，你已发起提现申请！ ", "color" => "#173177"],
            "keyword1" => ["value" => ($msg['refund'] . '元'), "color" => "#173177"],
            "keyword2" => ["value" => date('Y-m-d H:i:s', $msg['request_time']), "color" => "#173177"],
            "remark" => ["value" => "你好！发起提现后，款项将原路退回原支付账户。点击详情查看余额。", "color" => TEMPLATE_REMARK_FONT_COLOR]
        ]
    ];
}

function getWechatRefundFeeMsg($msg) {
    return [
        "touser" => $msg['openid'],
        "template_id" => WX_TEMPLATE_REFUND_FEE,
        "url" => "http://" . SERVER_DOMAIN . "/index.php?mod=wechat&act=user&opt=center#/userWallet",
        "data" => [
            "first" => ["value" => '你好，你有一笔费用退还信息。', "color" => "#173177"],
            "keyword1" => ["value" => $msg['orderid'], "color" => "#173177"],
            "keyword2" => ["value" => $msg['refund'] . '元', "color" => "#173177"],
            "remark" => ["value" => '费用已退还至用户中心，点击详情查看余额。如有疑问，请致电' . CUSTOMER_SERVICE_PHONE . '。', "color" => TEMPLATE_REMARK_FONT_COLOR]
        ]
    ];
}

function getWechatLoseUmbrellaMsg($msg) {
    return [
        "touser" => $msg['openid'],
        "template_id" => WX_TEMPLATE_LOSE_UMBRELLA,
        "url" => "http://" . SERVER_DOMAIN . "/index.php?mod=wechat&act=user&opt=center#/userRecord",
        "data" => [
            "first" => ["value" => '接收到您发起的登记遗失申请，已从押金中扣除费用'.$msg['price'].'元', "color" => "#173177"],
            "keyword1" => ["value" => $msg['borrow_station_name'], "color" => "#173177"],
            "keyword2" => ["value" => $msg['borrow_time'], "color" => "#173177"],
            "keyword3" => ["value" => $msg['handle_time'], "color" => "#173177"],
            "keyword4" => ["value" => $msg['order_id'], "color" => "#173177"],
            "remark" => ["value" => '感谢您对JJ伞的支持。如有疑问，请致电' . CUSTOMER_SERVICE_PHONE . '。', "color" => TEMPLATE_REMARK_FONT_COLOR]
        ]
    ];
}

function getAlipayReturnUmbrellaMsg($msg) {
    return [
        "to_user_id" => $msg['openid'],
        "template" => [
            "template_id" => ALIPAY_TEMPLATE_UMBRELLA_RETURN,
            "context" => [
                "head_color"  => "#173177",
                "url" => "http://" . SERVER_DOMAIN . "/index.php?mod=wechat&act=user&opt=center#/userWallet",
                "action_name" => '查看详情',
                "first" =>    ["value" => "你好，你已成功归还一把雨伞！", "color" => "#173177"],
                "keyword1" => ["value" => $msg['return_station_name'], "color" => "#173177"],
                "keyword2" => ["value" => date('Y-m-d H:i:s', $msg['return_time']), "color" => "#173177"],
                "keyword3" => ["value" => humanTime($msg['used_time']), "color" => "#173177"],
                "keyword4" => ["value" => $msg['orderid'], "color" => "#173177"],
                "remark" =>   ["value" => "此次租借产生费用{$msg['price']}，点击详情提取剩余押金。如有疑问，请致电" . CUSTOMER_SERVICE_PHONE . "。", "color" => TEMPLATE_REMARK_FONT_COLOR]
            ],
        ]
    ];
}

function getAlipayBorrowUmbrellaMsg($msg) {
    return [
        "to_user_id" => $msg['openid'],
        "template" => [
            "template_id" => ALIPAY_TEMPLATE_UMBRELLA_BORROW,
            "context" => [
                "head_color"  => "#173177",
                "url" => "http://" . SERVER_DOMAIN . "/index.php?mod=wechat&act=user&opt=center#/userRecord",
                "action_name" => '查看详情',
                "first" =>    ["value" => "你好，你已成功租借一把雨伞。", "color" => "#173177"],
                "keyword1" => ["value" => $msg['borrow_station_name'], "color" => "#173177"],
                "keyword2" => ["value" => date('Y-m-d H:i:s', $msg['borrow_time']), "color" => "#173177"],
                "keyword3" => ["value" => $msg['orderid'], "color" => "#173177"],
                "remark" =>   ["value" => "雨伞借出成功，感谢你的使用。", "color" => TEMPLATE_REMARK_FONT_COLOR]
            ],
        ]
    ];
}

function getAlipayBorrowFailMsg($msg) {
    return [
        "to_user_id" => $msg['openid'],
        "template" => [
            "template_id" => ALIPAY_TEMPLATE_BORROW_FAIL,
            "context" => [
                "head_color"  => "#173177",
                "url" => "http://" . SERVER_DOMAIN . "/index.php?mod=wechat&act=user&opt=pay#/oneKeyUse",
                "action_name" => '查看详情',
                "first" =>    ["value" => "你好，雨伞租借失败了。", "color" => "#173177"],
                "keyword1" => ["value" => $msg['borrow_station_name'], "color" => "#173177"],
                "keyword2" => ["value" => date('Y-m-d H:i:s', $msg['borrow_time']), "color" => "#173177"],
                "remark" =>   ["value" => "雨伞借出失败，点击详情重新借伞。", "color" => TEMPLATE_REMARK_FONT_COLOR]
            ],
        ]
    ];
}

function getAlipayReturnRemindMsg($msg)  {
    return [
        "to_user_id" => $msg['openid'],
        "template" => [
            "template_id" => ALIPAY_TEMPLATE_RETURN_REMIND,
            "context" => [
                "head_color"  => "#173177",
                "url" => "http://" . SERVER_DOMAIN . "/index.php?mod=wechat&act=user&opt=center#/userRecord",
                "action_name" => '查看详情',
                "first" => ["value" => "请尽快归还租借的雨伞。", "color" => "#173177"],
                "keyword1" => ["value" => humanTime($msg['difftime']), "color" => "#173177"], //租借时长
                "keyword2" => ["value" => $msg['usefee'] . "元", "color" => "#173177"], //产生费用
                "remark" => ["value" => "你租借的雨伞已经产生了{$msg['usefee']}元的租借费用，点击详情查看租借记录。如有疑问，请致电" . CUSTOMER_SERVICE_PHONE . "。", "color" => TEMPLATE_REMARK_FONT_COLOR]
            ],
        ]
    ];
}

function getAlipayWithdrawApplyMsg($msg) {
    return [
        "to_user_id" => $msg['openid'],
        "template" => [
            "template_id" => ALIPAY_TEMPLATE_WITHDRAW_APPLY,
            "context" => [
                "head_color"  => "#173177",
                "url" => "http://" . SERVER_DOMAIN . "/index.php?mod=wechat&act=user&opt=center#/userWallet",
                "action_name" => '查看详情',
                "first" => ["value" => "你好，你已发起提现申请！ ", "color" => "#173177"],
                "keyword1" => ["value" => ($msg['refund'] . '元'), "color" => "#173177"],
                "keyword2" => ["value" => date('Y-m-d H:i:s', $msg['request_time']), "color" => "#173177"],
                "remark" => ["value" => "你好！发起提现后，款项将原路退回原支付账户。点击详情查看余额。", "color" => TEMPLATE_REMARK_FONT_COLOR]
            ],
        ]
    ];

}

function getAlipayRefundFeeMsg($msg) {
    return [
        "to_user_id" => $msg['openid'],
        "template" => [
            "template_id" => ALIPAY_TEMPLATE_REFUND_FEE,
            "context" => [
                "head_color"  => "#173177",
                "url" =>  "http://" . SERVER_DOMAIN . "/index.php?mod=wechat&act=user&opt=center#/userWallet",
                "action_name" => '查看详情',
                "first" => ["value" => '你好，你有一笔费用退还信息。', "color" => "#173177"],
                "keyword1" => ["value" => $msg['orderid'], "color" => "#173177"],
                "keyword2" => ["value" => $msg['refund'] . '元', "color" => "#173177"],
                "remark" => ["value" => '费用已退还至用户中心，点击详情查看余额。如有疑问，请致电' . CUSTOMER_SERVICE_PHONE . '。', "color" => TEMPLATE_REMARK_FONT_COLOR]
            ],
        ]
    ];
}

function getAlipayLoseUmbrellaMsg($msg) {
    return [
        "to_user_id" => $msg['openid'],
        "template" => [
            "template_id" => ALIPAY_TEMPLATE_LOSE_UMBRELLA,
            "context" => [
                "head_color"  => "#173177",
                "first" => ["value" => '接收到您发起的登记遗失申请，已从押金中扣除费用'.$msg['price'].'元', "color" => "#173177"],
                "url" => "http://" . SERVER_DOMAIN . "/index.php?mod=wechat&act=user&opt=center#/userRecord",
                "action_name" => '查看详情',
                "keyword1" => ["value" => $msg['borrow_station_name'], "color" => "#173177"],
                "keyword2" => ["value" => $msg['borrow_time'], "color" => "#173177"],
                "keyword3" => ["value" => $msg['handle_time'], "color" => "#173177"],
                "keyword4" => ["value" => $msg['order_id'], "color" => "#173177"],
                "remark" => ["value" => '感谢您对JJ伞的支持。如有疑问，请致电' . CUSTOMER_SERVICE_PHONE . '。', "color" => TEMPLATE_REMARK_FONT_COLOR]
            ]
        ]
    ];
}

function getWeappBorrowSuccessMsg($msg)
{
    return [
        'touser'      => $msg['openid'],
        'template_id' => WEAPP_TEMPLATE_BORROW_SUCCESS,
        'page'        => 'pages/record/record',
        'form_id'     => $msg['form_id'],
        'data'        => [
            'keyword1' => [
                'value' => $msg['borrow_station_name'],
                'color' => '#173177',
            ],
            'keyword2' => [
                'value' => date('Y-m-d H:i:s', $msg['borrow_time']),
                'color' => '#173177',
            ],
            'keyword3' => [
                'value' => $msg['orderid'],
                'color' => '#173177',
            ],
        ],
    ];
}

function getWeappBorrowFailMsg($msg)
{
    return [
        'touser'      => $msg['openid'],
        'template_id' => WEAPP_TEMPLATE_BORROW_FAIL,
        'page'        => 'pages/map/map',
        'form_id'     => $msg['form_id'],
        'data'        => [
            'keyword1' => [
                'value' => $msg['borrow_station_name'],
                'color' => '#173177',
            ],
            'keyword2' => [
                'value' => date('Y-m-d H:i:s', $msg['borrow_time']),
                'color' => '#173177',
            ],
            'keyword3' => [
                'value' => '退回用户中心',
                'color' => '#173177',
            ],
        ],
    ];
}

function getWeappReturnUmbrellaMsg($msg)
{
    return [
        'touser'      => $msg['openid'],
        'template_id' => WEAPP_TEMPLATE_UMBRELLA_RETURN,
        'page'        => 'pages/map/map',
        'form_id'     => $msg['form_id'],
        'data'        => [
            'keyword1' => [
                'value' => $msg['return_station_name'],
                'color' => '#173177',
            ],
            'keyword2' => [
                'value' => date('Y-m-d H:i:s', $msg['return_time']),
                'color' => '#173177',
            ],
            'keyword3' => [
                'value' => humanTime($msg['used_time']),
                'color' => '#173177',
            ],
            'keyword4' => [
                'value' => $msg['orderid'],
                'color' => '#173177',
            ],
            'keyword5' => [
                'value' => $msg['price'],
                'color' => '#173177',
            ],
        ],
    ];
}

function getWeappReturnRemindMsg($msg)
{
    return [
        'touser'      => $msg['openid'],
        'template_id' => WEAPP_TEMPLATE_RETURN_REMIND,
        'page'        => 'pages/personal/personal',
        'form_id'     => $msg['form_id'],
        'data'        => [
            'keyword1' => [
                'value' => humanTime($msg['difftime']),
                'color' => '#173177',
            ],
            'keyword2' => [
                'value' => $msg['usefee'] . "元",
                'color' => '#173177',
            ],
        ],
    ];
}

function getWeappWithdrawApplyMsg($msg)
{
    return [
        'touser'      => $msg['openid'],
        'template_id' => WEAPP_TEMPLATE_WITHDRAW_APPLY,
        'page'        => 'pages/walletDetail/walletDetail',
        'form_id'     => $msg['form_id'],
        'data'        => [
            'keyword1' => [
                'value' => $msg['refund'] . '元',
                'color' => '#173177',
            ],
            'keyword2' => [
                'value' => date('Y-m-d H:i:s', $msg['request_time']),
                'color' => '#173177',
            ],
        ],
    ];
}

function getWeappRefundFeeMsg($msg)
{
    return [
        'touser'      => $msg['openid'],
        'template_id' => WEAPP_TEMPLATE_REFUND_FEE,
        'page'        => 'pages/personal/personal',
        'form_id'     => $msg['form_id'],
        'data'        => [
            'keyword1' => [
                'value' => $msg['orderid'],
                'color' => '#173177',
            ],
            'keyword2' => [
                'value' => $msg['refund'] . '元',
                'color' => '#173177',
            ],
        ],
    ];
}

function getWeappLoseUmbrellaMsg($msg)
{
    return [
        'touser'      => $msg['openid'],
        'template_id' => WEAPP_TEMPLATE_LOSE_UMBRELLA,
        'page'        => 'pages/record/record',
        'form_id'     => $msg['form_id'],
        'data'        => [
            'keyword1' => [
                'value' => $msg['borrow_station_name'],
                'color' => '#173177',
            ],
            'keyword2' => [
                'value' => $msg['borrow_time'],
                'color' => '#173177',
            ],
            'keyword3' => [
                'value' => $msg['handle_time'],
                'color' => '#173177',
            ],
            'keyword4' => [
                'value' => $msg['order_id'],
                'color' => '#173177',
            ],
        ],
    ];
}

/** Json数据格式化
* @param  Mixed  $data   数据
* @param  String $indent 缩进字符，默认4个空格
* @return string
*/
function jsonFormat($data, $indent=null){

    // 对数组中每个元素递归进行urlencode操作，保护中文字符
    array_walk_recursive($data, 'jsonFormatProtect');

    // json encode
    $data = json_encode($data);

    // 将urlencode的内容进行urldecode
    $data = urldecode($data);

    // 缩进处理
    $ret = '';
    $pos = 0;
    $length = strlen($data);
    $indent = isset($indent)? $indent : '    ';
    $newline = "\n";
    $prevchar = '';
    $outofquotes = true;

    for($i=0; $i<=$length; $i++){

        $char = substr($data, $i, 1);

        if($char=='"' && $prevchar!='\\'){
            $outofquotes = !$outofquotes;
        }elseif(($char=='}' || $char==']') && $outofquotes){
            $ret .= $newline;
            $pos --;
            for($j=0; $j<$pos; $j++){
                $ret .= $indent;
            }
        }

        $ret .= $char;

        if(($char==',' || $char=='{' || $char=='[') && $outofquotes){
            $ret .= $newline;
            if($char=='{' || $char=='['){
                $pos ++;
            }

            for($j=0; $j<$pos; $j++){
                $ret .= $indent;
            }
        }

        $prevchar = $char;
    }

    return $ret;
}

function makeFeeStr($fee_strategy) {
    $feeSettings = is_array($fee_strategy) ? $fee_strategy : json_decode($fee_strategy, true);
	$feeUnit = $feeSettings['fee_time'] . timeUnit($feeSettings['fee_unit']);
	$fee = $feeSettings['fee'];

	if ( !empty($feeSettings['fixed_time']) && $feeSettings['fixed_time'] != 0 ) {
	    if(!empty($fee) && !empty($feeUnit)){
		    $fixStr = $feeSettings['fixed_time'] . timeUnit($feeSettings['fixed_unit']) . '内' . (empty($feeSettings['fixed'])? '免费' : ($feeSettings['fixed'] . '元')) . "，逾期{$fee}元/{$feeUnit}";
        } else {
            $fixStr = $feeSettings['fixed_time'] . timeUnit($feeSettings['fixed_unit']) . '内' . (empty($feeSettings['fixed'])? '免费' : ($feeSettings['fixed'] . '元'));
        }
    } else {
		$fixStr = "{$fee}元/{$feeUnit}";
	}
	$max_fee_time = $feeSettings["max_fee_time"];
	$max_fee_unit = timeUnit($feeSettings["max_fee_unit"]);
	$max_fee      = $feeSettings['max_fee'];

	if ( !empty($max_fee) && $max_fee != 0 ) {
		if (!empty($max_fee_time)) {
		    if($max_fee_time == 1){
		    	$maxStr = "每{$max_fee_unit}最高收费{$max_fee}元";
            } else {
                $maxStr = "每{$max_fee_time}{$max_fee_unit}最高收费{$max_fee}元";
            }
        } else {
			$maxStr = "最高收费{$max_fee}元";
		}
	}

	return $fixStr. $maxStr;
}

function makeFeeStrForZhima($feeSettings) {
    // 只显示固定收费和固定外收费 14字符以内
	$feeUnit = $feeSettings['fee_time'] . timeUnit($feeSettings['fee_unit']);
	$fee = $feeSettings['fee'];
	// 固定收费和固定外收费
	if ( !empty($feeSettings['fixed_time']) && $feeSettings['fixed_time'] != 0 ) {
		if(empty($feeSettings['fixed'])) {
			$fixStr = $feeSettings['fixed_time'] . timeUnit($feeSettings['fixed_unit']) . '免费';
		} else {
			$fixStr = $feeSettings['fixed_time'] . timeUnit($feeSettings['fixed_unit']) . $feeSettings['fixed'] . '元';
		}
        return '首' . $fixStr . '，' . $feeUnit . $fee . '元';
    // 只有固定外收费
	} else {
		$fixStr = "{$fee}元/{$feeUnit}";
		return $fixStr;
	}

}

function makeFeeStrNew($fee_strategy) {
	$feeSettings = json_decode($fee_strategy, true);
	$feeUnit = $feeSettings['fee_time'] . timeUnit($feeSettings['fee_unit']);
	$fee = $feeSettings['fee'];

	if ( !empty($feeSettings['fixed_time']) && $feeSettings['fixed_time'] != 0 ) {
		$fixStr = '使用超过' . $feeSettings['fixed_time'] . timeUnit($feeSettings['fixed_unit']) . '后' . "，按{$fee}元/{$feeUnit}收费，";
	} else {
		$fixStr = "{$fee}元/{$feeUnit}。";
	}
	$max_fee_time = $feeSettings["max_fee_time"];
	$max_fee_unit = timeUnit($feeSettings["max_fee_unit"]);
	$max_fee      = $feeSettings['max_fee'];

	if ( !empty($max_fee) && $max_fee != 0 ) {
		if ($max_fee_time == 1) {
			$maxStr = "每{$max_fee_unit}最高收费{$max_fee}元。";
		} else {
			$maxStr = "每{$max_fee_time}{$max_fee_unit}最高收费{$max_fee}元。";
		}
	}

	return $fixStr. $maxStr;
}

function transpose($arr) {
	for($j=0; $j<count($arr[0]); $j++){
		$transposed_arr[$j] = array();   //确定转置后的数组有几行
	}
	for($i=0; $i<count($arr); $i++){
		for($j=0; $j<count($arr[$i]); $j++){
			$transposed_arr[$j][$i] = $arr[$i][$j];   //行列互换
		}
	}
	return $transposed_arr;
}

function cal_crc ( $str, $crc = 0x385da3ca, $poly = 0x7cb5e37d ) {
	$len = strlen($str);
	$arr = str_split($str);
	$mod = $len % 4;
	if ( $mod > 0 ) {
		$mod = 4 - $mod;
	}

	$len = $len + $mod; $i = 0;

	while ( $len-- ) {
		$xbit = 0b10000000;

		if ( $len < $mod ) {
			$data = 0;
		} else {
			$data = ord($arr[$i++]);
		}

		for ( $bits = 0; $bits < 8; $bits++ ) {
			if ( $crc & 0x80000000 ) {
				$crc = $crc << 1;
				$crc = $crc ^ $poly;
			} else {
				$crc = $crc << 1;
			}

			if ( $data & $xbit ) {
				$crc = $crc ^ $poly;
			}
			$xbit = $xbit >> 1;
		}
	}
	return $crc & 0xFFFFFFFF;
}

function create_excel_column($data, $column_name, $column_title, $all_data = '') {
	$row_data = array_column($data, $column_name);
	array_unshift($row_data, $column_title);
	array_push($row_data, $all_data);
	return $row_data;
}

function export_excel($arrayData, $name = '01simple', $begin_grid = 'A1') {
	$objPHPExcel = new PHPExcel();
	$objPHPExcel->getProperties()->setCreator("Maarten Balliauw")
                                 ->setLastModifiedBy("Maarten Balliauw")
                                 ->setTitle("Office 2007 XLSX Test Document")
                                 ->setSubject("Office 2007 XLSX Test Document")
                                 ->setDescription("Test document for Office 2007 XLSX, generated using PHP classes.")
                                 ->setKeywords("office 2007 openxml php")
                                 ->setCategory("Test result file");

    // print_r($sheetarray);
    // exit;
    $objPHPExcel->getActiveSheet()->fromArray($arrayData, null, $begin_grid, true);
    // Rename worksheet
    $objPHPExcel->getActiveSheet()->setTitle('Simple');

    // Set active sheet index to the first sheet, so Excel opens this as the first sheet
    $objPHPExcel->setActiveSheetIndex(0);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $name . '.xlsx"');
    header('Cache-Control: max-age=0');
    // If you're serving to IE 9, then the following may be needed
    header('Cache-Control: max-age=1');

    // If you're serving to IE over SSL, then the following may be needed
    header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
    header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
    header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
    header ('Pragma: public'); // HTTP/1.0

    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
    $objWriter->save('php://output');
}

/*
    生成街借伞订单号
*/
function generate_order_id(){
	$date = getdate(time());
	$year = $date['year'];
	$mon = $date['mon'];
	$mday = $date['mday'];
	$h = $date['hours'];
	$m = $date['minutes'];
	$s = $date['seconds'];
	$sn = rand(1, 99999);
	return sprintf("jjsan-%u%02u%02u-%02u%02u%02u-%05u", $year, $mon, $mday, $h, $m, $s, $sn );
}

/*
   获取收费信息用于芝麻信用显示
   $sid 站点ID
   返回数组 [0=>单位时间租借费用, 1=>费用单位, 2=>租借策略提示, 3=>租借周期(单位为天)]
 */
function getFeeInfoForZhima($sid, $deposit) {
	if(empty($sid)){
		return NULL;
    }
	$ret = array();
    $feeSettings = ct('shop_station')->getFeeSettingsByStationId($sid);
    $ret[0] = $feeSettings['fee'];
    // 芝麻信用只支持单位 HOUR DAY
    if($feeSettings['fee_unit'] == 3600){
        $dayFee = 24 * $feeSettings['fee'];
        if($feeSettings['fee_time'] < 2){
            $ret[0] = $feeSettings['fee'];
            $ret[1] = 'HOUR_YUAN';
        } else {
            $dayFee = 24 / $feeSettings['fee_time'] * $feeSettings['fee'];
            $ret[0] = $dayFee;
            $ret[1] = 'DAY_YUAN';
        }
    } else {
        $dayFee = $feeSettings['fee'];
    }
    $ret[2] = makeFeeStrForZhima($feeSettings);
    $now = time();
	$days = ceil($deposit / $dayFee);
	$expire_time = date('Y-m-d H:i:s', $now + $days * 24 * 3600);
	$ret[3] = $expire_time;
	LOG::DEBUG('zhima fee str: ' . print_r($ret, 1));
	return $ret;
}

function create_report_array($order , $time_tag )
{
    $return_stations = array();
    if (is_array($order)) {
        foreach ($order as $value) {
            if ($value['borrow_time'] == 0) {
                continue;
            }
            $hour = date('H',$value['borrow_time']);
            $index = $hour.'-'.($hour+1) ;
            $arr[$index] += 1 ;
            // $machines['day']['income'] += $value['usefee'];
            if ($value['return_shop_station_id'] > 0 && !in_array($value['return_shop_station_id'] , $return_stations)) {
                $return_stations[] = $value['return_shop_station_id'];
                $machines[$time_tag]['machine_amount'] += 1;
            }

            if ($value['usefee'] > 0) {
                $machines[$time_tag]['timeout']['count'] += 1;
                $machines[$time_tag]['timeout']['income'] += $value['usefee'];
            }
            else {
                $machines[$time_tag]['normal']['count'] += 1;
                $machines[$time_tag]['normal']['income'] += $value['usefee'];
            }
        }
        $machines[$time_tag]['machine_amount']    = $machines[$time_tag]['machine_amount'] | 0;
        $machines[$time_tag]['timeout']['count']  = $machines[$time_tag]['timeout']['count'] | 0;
        $machines[$time_tag]['normal']['count']   = $machines[$time_tag]['normal']['count'] | 0;
        $machines[$time_tag]['timeout']['income'] = empty($machines[$time_tag]['timeout']['income']) ? 0: $machines[$time_tag]['timeout']['income'];
        $machines[$time_tag]['normal']['income']  = empty($machines[$time_tag]['normal']['income']) ? 0 : $machines[$time_tag]['normal']['income'];
        $machines[$time_tag]['total_use']         = $machines[$time_tag]['timeout']['count'] + $machines[$time_tag]['normal']['count'];
        $machines[$time_tag]['total_income']      = $machines[$time_tag]['normal']['income'] + $machines[$time_tag]['timeout']['income'] ;

        return $machines;
    }
}

/**
 * @param $umbrellaid
 * @param $stationid
 * @param $slot
 * @param $returnTime
 * @param bool $isExceptionOrder 定时任务中异常订单处理,不更新库存,且订单最终状态是94
 * @return array|string
 */
function returnBackUmbrella($umbrellaid, $stationid, $slot, $returnTime, $isExceptionOrder = false) {

    // 允许雨伞表中有同一个站点同一个槽位的多条记录存在
    // 后台显示的时候以最近一条数据为准即可

    // 只处理订单借出的伞，新伞归还不在这里处理。

    $umbrella = ct('umbrella')->fetch($umbrellaid);
    $orderid = $umbrella['order_id'];
    $station = ct('station')->fetch($stationid);

    if ($orderid) {
        $orderInfo = ct('tradelog')->fetch($orderid);

        // @todo 下面2种情况是否需要更新库存?

        // 订单id不存在，回复归还成功
        if (!$orderInfo) {
            LOG::WARN("umbrella id: $umbrellaid, has not existed order id $orderid");
            return json_encode(makeErrorData(ERR_NORMAL, 'return umbrella back success', $umbrellaid, 'return_back'));
        }
        // 订单状态已完成，回复归还成功
        // @todo 其他订单状态待处理
        if ($orderInfo['status'] == ORDER_STATUS_RETURN) {
            LOG::WARN("duplicate return back, umbrella id: $umbrellaid, order id $orderid");
            return json_encode(makeErrorData(ERR_NORMAL, 'return umbrella back success', $umbrellaid, 'return_back'));
        }

        // 幂等判断, 过滤重复并发请求
        if(!ct('tradelog')->idempotent($orderid)) {
            LOG::DEBUG("return back repeated request umbrella: $umbrellaid, orderid: $orderid");
            return makeErrorData(ERR_NORMAL, "repeated request $umbrellaid, $orderid", $umbrellaid, 'return_back');
        }

        // 订单借出状态
        if ($orderInfo['status'] == ORDER_STATUS_RENT_CONFIRM) {
            // 订单借出时间按服务器时间算,归还时间按终端算
            // 服务器时间和终端时间不同步,可能会造成借出时间比归还时间早的情况发生
            if ($returnTime < $orderInfo['borrow_time']) {
                $returnTime = $orderInfo['borrow_time'];
            }
            // 雨伞使用时间
            $used_time = $returnTime - $orderInfo['borrow_time'];
            $mark = $umbrella['mark'];
            $address = $station['address'];

            if($used_time < 300){
                $mark += 1;
            } else {
                $mark = 0;
            }

            // 测试环境不启用这功能
            if (ENV_DEV) {
                $mark = 0;
            }

            // 有可能锁槽位命令发送失败了，mark会大于4
            if($mark >= 4){
                swAPI::slotLock($stationid, $slot);
                LOG::INFO("system lock umbrella, station: $stationid , slot: $slot , umid: $umbrellaid, orderid: $orderid");
                // 维护人员openid, 数据库直接手动写入，后面维护人员分级的时候再重新规划
                $openid = C::t('common_setting')->fetch('jjsan_umbrella_broken_notice_openid');
                if ($openid) {
                    $msg = [
                        'openid' => $openid,
                        'station_id' => $stationid,
                        'address' => $address,
                        'slot' => $slot,
                        'umbrella_id' => $umbrellaid,
                    ];
                    $mark = 0;
                    // 推送雨伞损坏信息
                    addMsgToQueue(PLATFORM_WX, TEMPLATE_TYPE_BROKEN_REMIND, $msg);
                }
            }

            // 雨伞收费
            $fee = calcFee($orderid, $orderInfo['borrow_time'], $returnTime);
            $fee = min($fee, $orderInfo['price']);

            LOG::DEBUG('order id: ' . $orderid . ' fee: ' . $fee);

            // 增加是否零收费人员判断 只针对微信平台
            if  ($orderInfo['platform'] == PLATFORM_WX) {
                $zeroFeeUserList = ct('common_setting')->fetch('zero_fee_user_list');
                if ($zeroFeeUserList['svalue']) {
                    $zeroFeeUser = json_decode($zeroFeeUserList['svalue'], true);
                    if (in_array($orderInfo['openid'], $zeroFeeUser)) {
                        $isZeroFeeUserOrder = true;
                        $fee = 0;
                        LOG::INFO("openid: {$orderInfo['openid']}, zero fee user, fee: " . $fee);
                    }
                }
            }

            // 优先使用商铺名称，其次商铺站点名称，最后是机器id
            $shopStation = ct('shop_station')->where(['station_id' => $stationid])->first();
            $shop = ct('shop') -> fetch($shopStation['shopid']);
            if($shop['name']){
                $title = $shop['name'];
            } elseif ($shopStation['title']){
                $title = $shopStation['title'];
            } else {
                $title = $stationid;
            }


            $message = $orderInfo['message'] ? unserialize($orderInfo['message']) : [];
            // 是否零收费用户
            if ($isZeroFeeUserOrder) $message['zero_fee_user'] = 1;
            // 芝麻订单refund_fee = 0，其他平台存实际退款
            $message['refund_fee'] = $orderInfo['platform'] == PLATFORM_ZHIMA ? 0 : round($orderInfo['price'] - $fee, 2);
            $message['return_slot'] = $slot;
            // 更新订单
            ct('tradelog')->update($orderid, [
                'status' => $isExceptionOrder ? ORDER_STATUS_RETURN_EXCEPTION_SYS_REFUND : ORDER_STATUS_RETURN, //正常流程是3，借出后同步是94
                'lastupdate' => time(),
                'return_station' => $stationid,
                'return_time' => $returnTime,
                'return_station_name' => $title,
                'usefee' => $fee,
                'return_shop_id' => $shop['id'],
                'return_shop_station_id' => $shopStation['id'],
                'return_city' => $shop['city'],
                'return_device_ver' => $station['device_ver'],
                'message' => serialize($message),
            ]);
            LOG::DEBUG("update order id: $orderid to return back");

            // 更新雨伞信息
            ct('umbrella')->update($umbrellaid,[
                'station_id' => $stationid,
                'order_id' => '',
                'status' => UMBRELLA_INSIDE,
                'sync_time' => time(),
                'slot' => $slot,
                'exception_time' => 0,
                'mark' => $mark,
            ]);
            LOG::DEBUG("update umbrella: {$umbrella['id']} to umbrella_inside status");

            // 正常归还需要更新库存
            if  (!$isExceptionOrder) {
                // 更新站点库存
                // @todo 用sql语句重写
                $usable = $station['usable'] + 1;
                $empty = $station['empty'] - 1;
                ct('station') -> update($stationid, [
                    'sync_time' => time(),
                    'usable' => $usable,
                    'empty' => $empty
                ]);
                LOG::DEBUG('update station umbrellas empty and usable status');
            }

            // 更新账户余额,收取费用,退回剩余押金
            $isZhima = $orderInfo['platform'] == PLATFORM_ZHIMA;
            if(! $isZhima) {
                // 退款
                $userInfo = ct('user')->fetch($orderInfo['uid']);
                ct('user')->returnBack($userInfo['id'], $orderInfo['price'] - $fee, $orderInfo['price']);
                LOG::DEBUG("update user: {$userInfo['id']} , openid: {$orderInfo['openid']} usblemoney, deposit: {$orderInfo['price']} , fee: $fee");
                if ($fee > 0) {
                    // 记录用户流水
                    ct('wallet_statement')->insert([
                        'uid' => $orderInfo['uid'],
                        'related_id' => $orderid,
                        'type' => WALLET_TYPE_PAID,
                        'amount' => $fee,
                        'time' => date('Y-m-d H:i:s'),
                    ]);
                }
            } else {
                // 调用结算接口, 成功后更新为 查询状态
                require_once 'lib/alipay/AlipayAPI.php';
                $zmOrder = ct('trade_zhima')->fetch($orderid);
                $params = [
                    'order_no'          => $zmOrder['zhima_order'],
                    'product_code'      => 'w1010100000000002858',
                    'restore_time'      => date('Y-m-d H:i:s', $returnTime),
                    'pay_amount_type'   => 'RENT',
                    'pay_amount'        => $fee,
                    'restore_shop_name' => $station['title'],
                ];
                if ($fee > 0) {
                    // 记录用户流水
                    ct('wallet_statement')->insert([
                        'uid' => $orderInfo['uid'],
                        'related_id' => $orderid,
                        'type' => WALLET_TYPE_ZHIMA_PAID_UNCONFIRMED,
                        'amount' => $fee,
                        'time' => date('Y-m-d H:i:s'),
                    ]);
                }
                $resp = AlipayAPI::zhimaOrderRentComplete($params);
                // 值为空时，表示请求异常了（响应超时异常，状态码非200等）
                if (empty($resp)) {
                    LOG::ERROR('zhima order complete fail, orderid: ' . $orderid);
                    // 放到定时任务处理
                    ct('trade_zhima')->update($orderid,
                        [
                            'status'               => ZHIMA_ORDER_COMPLETE_WAIT,
                            'update_time'          => time()
                        ]
                    );
                } else {
                    LOG::DEBUG('zhima complete result: ' . print_r($resp, true));
                    // 芝麻订单只能检查是否订单完成（不能检查是否扣款成功）
                    if(! empty($resp->code) && $resp->code == 10000) {
                        LOG::DEBUG('zhima order complete success, orderid: ' . $orderid);
                        // 检查是否扣款成功，放到定时任务处理。
                        ct('trade_zhima')->update($orderid,
                            [
                                'status'               => ZHIMA_ORDER_QUERY_WAIT,
                                'alipay_fund_order_no' => $resp->alipay_fund_order_no,
                                'update_time'          => time()
                            ]
                        );
                        // 订单结束失败
                    } else if(! empty($resp->code) && $resp->code == 40004 && $resp->sub_code == 'UNITRADE_WITHHOLDING_PAY_FAILED') {
                        LOG::ERROR('zhima order UNITRADE_WITHHOLDING_PAY_FAILED, orderid: ' . $orderid);
                        // 放到定时任务处理
                        ct('trade_zhima')->update($orderid,
                            [
                                'status'               => ZHIMA_ORDER_PAY_FAIL_QUERY_RETRY,
                                'update_time'          => time()
                            ]
                        );
                    } else {
                        LOG::ERROR('zhima order complete fail, orderid: ' . $orderid);
                        // 放到定时任务处理
                        ct('trade_zhima')->update($orderid,
                            [
                                'status'               => ZHIMA_ORDER_COMPLETE_WAIT,
                                'update_time'          => time()
                            ]
                        );
                    }
                }
            }

            $msg = [
                'openid' => $orderInfo['openid'],
                'orderid' => $orderid,
                'return_time' => $returnTime,
                'return_station_name' => $title,
                'used_time' => $used_time,
                'price' => $fee . '元',
            ];

            // 推送还伞信息
            addMsgToQueue($orderInfo['platform'],  TEMPLATE_TYPE_RETURN_UMBRELLA, $msg);

            // @todo 未考虑写入失败的情况
            //json_encode(makeErrorData(ERR_SERVER_DB_FAIL, 'server db fail', $umbrellaid, 'return_back'));

            LOG::DEBUG('return umbrella back success, umbrella id: ' . $umbrellaid . ' station id: '. $stationid);
            return json_encode(makeErrorData(ERR_NORMAL, 'return umbrella back success', $umbrellaid, 'return_back'));

        }

        // 其他异常状态
        LOG::INFO("umbrella : $umbrellaid exception, " . print_r($orderInfo, 1));
        return json_encode(makeErrorData(ERR_NORMAL, 'return umbrella back success', $umbrellaid, 'return_back'));

    } else {

        $station = ct('station')->fetch($stationid);
        ct('station') -> update($stationid, [
            'sync_time' => time(),
            'usable' => $station['usable'] + 1,
            'empty' => $station['empty'] - 1,
        ]);
        LOG::WARN("umbrella id: $umbrellaid, has not order id, stationid: $stationid , usable: " .($station['usable']+1) . " , empty: " . ($station['empty'] - 1));
        return json_encode(makeErrorData(ERR_NORMAL, 'return umbrella back success', $umbrellaid, 'return_back'));
    }
}

/*
  芝麻订单创建通知
*/
function zhimaCreateNotify($orderid, $zhimaOrder) {
	LOG::DEBUG('zhima order borrow success, orderid: ' . $orderid);
	LOG::DEBUG('start zhima borrow process');
	if (ct('trade_zhima')->fetch($orderid)) {
	    LOG::INFO("zhima orderid: $orderid has been created");
	    return ;
    }
    $ret = ct('trade_zhima')->insert([
        'orderid' => $orderid,
        'zhima_order'=> $zhimaOrder,
        'status' => ZHIMA_ORDER_CREATE,
        'create_time' => time(),
        'update_time' => time(),
    ], false, false ,true);
	if(! $ret) {
		LOG::ERROR("insert zhima order error: orderid $orderid , zhimaorder $zhimaOrder ");
		exit; //必须要退出程序，不返回success，才能保证芝麻消息再次推送消息过来
	} else {
		// 更新芝麻信用的信息, 押金为0
		notifyOrderPaid($orderid, 0);
		LOG::DEBUG('zhima notify business finish');
	}
	LOG::DEBUG('end of zhima borrow process');
}

function getCitiesByProvince($province, $tree) {
    foreach ($tree as $v) {
        if($v['province'] == $province) {
            foreach($v['city'] as $vv) {
                $tmp[] = $vv['name'];
            }
        }
    }
    return $tmp;
}

function getAreasByCity($province, $city, $tree) {
    foreach ($tree as $v) {
        if($v['province'] == $province) {
            foreach($v['city'] as $vv) {
                if($vv['name'] == $city) {
                    $tmp = $vv['area'];
                }
            }
        }
    }
    return $tmp;
}
function implode_with_key($assoc, $inglue = '=', $outglue = '&') {
    $return = '';

    foreach ($assoc as $tk => $tv) {
        $return .= $outglue . $tk . $inglue . $tv;
    }

    return substr($return, strlen($outglue));
}

function ct($tableName) {
    $t = '#' . PLUGIN_NAME . '#' . PLUGIN_NAME . '_' . $tableName;
    return C::t($t);
}

function defaultWeiXinMsg() {
    return 'scan event';
}

function handleAlipayTextMsg($msg) {
    $msg = trim($msg);
    $keywords = array(
        array('0', 'id', 'ID'),
    );
    if (likeIn($msg, $keywords[0])) {
        AlipayAPI::replyTextMsg( AlipayAPI::$msgData['client'] );
        exit;
    }
}

function handleWechatTextMsg($msg) {
    $msg = trim($msg);
    $keywords = array(
        array('id', 'ID'),
    );
    $revisable_keywords = json_decode( C::t('common_setting')->fetch('jjsan_wechat_keywords'), true );
    $replyMsg           = json_decode( C::t('common_setting')->fetch('jjsan_wechat_replyMsg'), true );
    $defaultMsg         = json_decode( C::t('common_setting')->fetch('jjsan_wechat_defaultMsg'), true );

    if (likeIn($msg, $keywords[0])) {
        wxAPI::replyTextMsg( wxAPI::$wxMsgData['client'] );
    } else {
        foreach ($revisable_keywords as $key => $value) {
            if (in_array($msg, $value)) {
                LOG::DEBUG('msg : '. $msg . 'replymsg : ' . $replyMsg[$key]);
                wxAPI::replyTextMsg($replyMsg[$key]);
                $flag = 1;
            }
        }
        if (!isset($flag) || $flag != 1) {
            wxAPI::replyTextMsg( $defaultMsg );
        }
    }
    exit;
}

function likeIn($msg, $keywords) {
    foreach($keywords as $key) {
        if(strpos($msg, $key) !== false)
            return true;
    }
    return false;
}

function isWhitelistForTest($uid) {
    $whitelist = json_decode( C::t('common_setting')->fetch('jjsan_whitelist'), true );
    return in_array($uid, $whitelist);
}

function getShortAddress($longAddress) {
    $longAddress = substr($longAddress, stripos($longAddress, '省') + 3);
    $longAddress = substr($longAddress, stripos($longAddress, '市') + 3);
    return $longAddress;
}

function checkProvenceCityAreaLegal($areaNavTree, $province, $city = '', $area = '') {
    foreach ($areaNavTree as $v) {
        // 省份为空
        if (empty($province)) {
            return false;
        }
        // 省
        if (empty($city) && empty($area)) {
            if ($v['province'] == $province) {
                return true;
            }
        }
        // 省市
        if ($city && empty($area)) {
            if ($v['province'] == $province) {
                foreach ($v['city'] as $vv) {
                    if ($vv['name'] == $city) {
                        return true;
                    }
                }
            }
        }
        // 省市区
        if ($city && $area) {
            if ($v['province'] == $province) {
                foreach ($v['city'] as $vv) {
                    if ($vv['name'] == $city) {
                        if (in_array($area, $vv['area'])) {
                            return true;
                        }
                    }
                }
            }
        }
    }
    return false;
}

function getShopStationNearby($lng, $lat) {
    $data['ak'] = BAIDU_MAP_AK;
    $data['location'] = "$lng,$lat";
    $data['geotable_id'] = GEOTABLE_ID;
    $data['radius'] = 2500;	//半径2.5km
    $data['page_size'] = 50; //返回数量，最大为50
    $data['sortby'] = "distance:1";
    $data['filter'] = "enable:1";
    $api = "http://api.map.baidu.com/geosearch/v3/nearby";
    $scurl = new sCurl( $api, 'GET', $data );
    $ret = $scurl->sendRequest();
    $ret = json_decode($ret, true);
    if($ret['status'] == 0) {
        $shop_stations = $ret['contents'];
        foreach ($shop_stations as $k => $v) {
            $shop_station_ids[] = $v['sid'];
        }
        return $shop_station_ids;
    }
    return false;
}

/**
 *
 * @param $qrcode
 * @param $uid
 * @param $sid
 * @param $platform
 * @return mixed
 */
function borrowForApi($qrcode, $uid, $sid, $platform){
    if (!$sid) {
        $sid = ct('qrcode')->fetch_by_qrcode($qrcode, $platform);
        if(!$sid){
            return Api::make(Api::ERROR_QR_CODE);
        }
    }

    // 检查站点是否在线
    if (!swAPI::isStationOnline($sid)) {
        return Api::make(2, [], '机器不在线');
    }

    $menuInfo = ct('menu')->fetch(1);

    $usable = ct('station')->getField($sid, 'usable');	//可用雨伞数量
    if($usable == 0){
        return Api::make(3, [], '无可借雨伞');
    }

    if($platform == PLATFORM_ALIPAY){
        $isZhima = !ct('tradelog')->hasUnfinishedZhimaOrder($uid);
    }

    if($isZhima){
        $platform = PLATFORM_ZHIMA;
        $openid = ct('user')->getField($uid, 'openid');

        LOG::DEBUG('start umbrella rent process, openid: ' . $openid . ' ,stationid:' . $sid . ' ,user id:' . $uid);

        // 商品信息
        $itemid = TAG_UMBRELLA;
        $menu = ct('menu')->fetch($itemid);
        $tag = $menu['tag'];
        $price = $menu['price'] * $menu['discount'];
        $totalsave = $menu['price'] - $price;
        $amount = 1;
        $totalprice = round($price * $amount, 2);

        LOG::DEBUG('zhima order start');

        $date = getdate(time());
        $year = $date['year'];
        $mon = $date['mon'];
        $mday = $date['mday'];
        $h = $date['hours'];
        $m = $date['minutes'];
        $s = $date['seconds'];
        $sn = rand(1, 99999);

        $orderid = sprintf("JJSAN-%u%02u%02u-%02u%02u%02u-%05u", $year, $mon, $mday, $h, $m, $s, $sn);
        LOG::DEBUG("openid: $openid, orderid: $orderid");

        $stationInfo = ct('station')->fetch($sid);
        $shopStationInfo = ct('shop_station')->where(['station_id' => $sid])->first();
        $shopInfo = ct('shop')->fetch($shopStationInfo['shopid']);
        if($shopInfo['name']){
            $title = $shopInfo['name'];
        } elseif ($shopStationInfo['title']){
            $title = $shopStationInfo['title'];
        } else {
            $title = $sid;
        }

        // message 中目前存2个信息 slot和img
        $ret = ct('tradelog')->insert([
            'orderid'                => $orderid,
            'price'                  => $totalprice,
            'baseprice'              => $totalsave,
            'openid'                 => $openid,
            'status'                 => ORDER_STATUS_WAIT_PAY,
            'message'                => '',
            'lastupdate'             => time(),
            'borrow_station'         => $sid,
            'borrow_station_name'    => $title ? : DEFAULT_STATION_NAME,
            'borrow_shop_id'         => $shopStationInfo['shopid'],
            'borrow_city'            => $shopInfo['city'],
            'borrow_device_ver'      => $stationInfo['device_ver'],
            'borrow_shop_station_id' => $shopStationInfo['id'],
            'tag'                    => $tag,
            'uid'                    => $uid,
            'shop_type'              => $shopInfo['type'],
            'seller_mode'            => 0, //直营或者代理 默认直营
            'refundno'               => ORDER_ZHIMA_NOT_REFUND,
            'platform'               => $platform,
            // 借出时间是从终端获取的，这里写入时间只是为了方便一些异常订单显示页面中有时间这个参数
            'borrow_time'            => time(),
        ], false, false, true);

        if (!$ret) {
            return Api::make(4, [], '服务器内部错误，请重试！');
        }

        $feeSettings = ct('shop_station')->getFeeSettings($shopStationInfo['id']);	//收费策略
        $feeStr = makeFeeStr(json_encode($feeSettings));
        ct('tradeinfo')->insert(['orderid' => $orderid, 'fee_strategy' => json_encode($feeSettings)]);
        LOG::DEBUG("save order id $orderid , fee strategy " . print_r($feeSettings, 1));
        LOG::DEBUG('orderid: ' . $orderid . ' total price: ' . $totalprice);

        $feeInfo = getFeeInfoForZhima($sid, $totalprice);

        // invoke_return_url 信用借还页面 https://docs.open.alipay.com/360/106720/
        $params = [
            "invoke_type"       => 'WINDOWS',
            "invoke_return_url" => "http://" . SERVER_DOMAIN . '/zhimanotify.php',
            "out_order_no"      => $orderid,
            "product_code"      => "w1010100000000002858", // 信用借还产品码（固定值）
            "goods_name"        => $menu['subject'],
            "rent_info"         => $feeInfo[2],
            "rent_unit"         => $feeInfo[1],
            "rent_amount"       => $feeInfo[0] ? $feeInfo[0] : 0, // 单位元, 精确到小数点后两位
            "deposit_amount"    => $totalprice, // 单位元, 精确到小数点后两位
            "deposit_state"     => 'Y', // 单位元, 精确到小数点后两位
            "expiry_time"       => $feeInfo[3],
            "borrow_shop_name"  => $stationInfo['title'] ? : DEFAULT_STATION_NAME,
        ];

        //建立请求
        require_once JJSAN_DIR_PATH . '/lib/alipay/AlipayAPI.php';
        $zhimaUrl = AlipayAPI::getZhimaRentOrderUrl($params);
        LOG::DEBUG('zhima url : ' . $zhimaUrl);
        $data = ['url' => $zhimaUrl, 'fee_strategy' => $feeStr];
        return Api::make(Api::SUCCESS, $data);
    }

    LOG::DEBUG("current station id: " . $sid);


    $usablemoney = ct('user')->getField($uid, 'usablemoney');	//用户可用金额
    $price = $menuInfo['price'] == (int)$menuInfo['price'] ? (int)$menuInfo['price'] : $menuInfo['price'];
    $shop_station_id = ct('shop_station')->getIdByStaionId($sid);
    //需要押金
    $feeSettings = ct('shop_station')->getFeeSettings($shop_station_id);	//收费策略

    $feeStr = makeFeeStr(json_encode($feeSettings));

    if (round($usablemoney, 2) >= round($price, 2)) {
        $need_pay = 0;
    }else {
        $need_pay = round($price-$usablemoney,2);
    }

    $data = [
        'sid'	        =>  $sid,
        'usable'        =>  $usable,
        'deposit_need'	=>	$price,
        'usable_money'	=>	$usablemoney,
        'fee_strategy'	=>	$feeStr,
        'need_pay'	    =>	$need_pay,
    ];
    return Api::make(Api::SUCCESS, $data);
}

function getPayInfo($stationid, $uid, $platform = null){
    if ($stationid <= 0) {
        return Api::make(3, [], '无可借雨伞');
    }
    if($platform == PLATFORM_WEAPP){
        $openid = ct('user_weapp')->select('openid')->where(['id'=>$uid])->first()['openid'];
    } else {
        $userInfo = ct('user')->fetch($uid);
        $platform = $userInfo['platform'];
        $openid = $userInfo['openid'];
    }

    LOG::DEBUG('start umbrella rent process, openid: ' . $openid . ' ,stationid:' . $stationid . ' ,user id:' . $uid);
    // 记录下点击确认支付事件
    $user = new User();
    $user->user_pay_event($uid);

    // 机器当前是否借出操作中
    if(ct('tradelog')->hasBorrowingOrder($stationid)) {
        return Api::make(2, [], "上一单未完成");
    }

    $usable = ct('station')->getField($stationid, 'usable');	//可用雨伞数量
    if($usable == 0){
        return Api::make(3, [], '无可借雨伞');
    }


    // 商品信息
    $menu = ct('menu')->fetch(1);
    $tag = $menu['tag'];
    $price = $menu['price'] * $menu['discount'];
    $totalsave = $menu['price'] - $price;
    $amount = 1;
    $flavor = $_GET['flavor'];
    $totalprice = round($price * $amount, 2);
    $usableMoney = ct('user')->getField($uid, 'usablemoney');
    $usbaleMoney = round($usableMoney, 2);
    LOG::DEBUG("user: $uid, usable: $usableMoney, totalprice: $totalprice");


    $date = getdate(time());
    $year = $date['year'];
    $mon = $date['mon'];
    $mday = $date['mday'];
    $h = $date['hours'];
    $m = $date['minutes'];
    $s = $date['seconds'];
    $sn = rand(1, 99999);

    $orderid = sprintf("JJSAN-%u%02u%02u-%02u%02u%02u-%05u", $year, $mon, $mday, $h, $m, $s, $sn);
    LOG::DEBUG("openid: $openid, orderid: $orderid");

    // 优先使用商铺名称，其次商铺站点名称，最后是机器id
    $stationInfo = ct('station')->fetch($stationid);
    $shopStationInfo = ct('shop_station')->where(['station_id' => $stationid])->first();
    $shopInfo = ct('shop')->fetch($shopStationInfo['shopid']);
    if($shopInfo['name']){
        $title = $shopInfo['name'];
    } elseif ($shopStationInfo['title']){
        $title = $shopStationInfo['title'];
    } else {
        $title = $stationid;
    }


    // message 中目前存2个信息 slot和img
    $ret = ct('tradelog')->insert([
        'orderid'                => $orderid,
        'price'                  => $totalprice,
        'baseprice'              => $totalsave,
        'openid'                 => $openid,
        'status'                 => ORDER_STATUS_WAIT_PAY,
        'message'                => '',
        'lastupdate'             => time(),
        'borrow_station'         => $stationid,
        'borrow_station_name'    => $title ? : DEFAULT_STATION_NAME,
        'borrow_shop_id'         => $shopStationInfo['shopid'],
        'borrow_city'            => $shopInfo['city'],
        'borrow_device_ver'      => $stationInfo['device_ver'],
        'borrow_shop_station_id' => $shopStationInfo['id'],
        'tag'                    => $tag,
        'uid'                    => $uid,
        'shop_type'              => $shopInfo['type'],
        'seller_mode'            => 0, //直营或者代理 默认直营
        'refundno'               => 0, //芝麻信用订单不能用于退款
        'platform'               => $platform,
        // 借出时间是从终端获取的，这里写入时间只是为了方便一些异常订单显示页面中有时间这个参数
        'borrow_time'            => time(),
    ], false, false, true);

    if (!$ret) {
        return Api::make(4, [], "服务器内部错误，请重试！");
    }

    //保存收费策略
    $feeSettings = ct('fee_strategy')->getStrategySettings($shopStationInfo['fee_settings']);
    ct('tradeinfo')->insert(['orderid' => $orderid, 'fee_strategy' => json_encode($feeSettings)]);
    LOG::DEBUG("save order id $orderid , fee strategy " . print_r($feeSettings, 1));
    LOG::DEBUG('orderid: ' . $orderid . ' total price: ' . $totalprice);

    // 雨伞支付押金
    if ($tag == TAG_UMBRELLA) {
        LOG::DEBUG('begin to pay in user account');
        $payRet = ct('user')->pay($uid, $totalprice);
        // 账户类押金支付
        if ($payRet) {
            $ret = ct('tradelog')->update($orderid, ['status' => ORDER_STATUS_PAID, 'refundno' => ORDER_NOT_REFUND, 'lastupdate' => time()]);
            if (!$ret) {
                LOG::ERROR("pay direct success, but status update fail. orderid:" . $orderid);
                exit;
            } else {
                LOG::DEBUG("pay direct success, status update success, orderid:" . $orderid);
            }
            // 出伞命令
            swAPI::borrowUmbrella($stationid, $orderid);
            LOG::DEBUG('pay in account successfully');
            $data = ['paytype' => 1, 'orderid' => $orderid];
            return Api::make(Api::SUCCESS, $data);
        }

        // 押金不足,需要在线支付
        if($totalprice > $usableMoney) {
            $totalprice = $totalprice - $usableMoney;
            LOG::DEBUG('user need pay money online: ' . $totalprice);
        }

        switch ($platform) {

            # 微信支付
            case PLATFORM_WX:
            case PLATFORM_WEAPP:
                $tools = new JsApiPay();
                $input = new WxPayDataBase();
                $input->values = [
                    'body'         => $menu['subject'],
                    'attach'       => "Attach",
                    'out_trade_no' => $orderid,
                    'total_fee'    => round(($totalprice * 100)), //单位是分, 四舍五入
                    'time_start'   => date("YmdHis"),
                    'time_expire'  => date("YmdHis", time() + 600),
                    'goods_tag'    => "NOTAG",
                    'notify_url'   => "http://" . SERVER_DOMAIN . "/wxpaynotify.php",
                    'trade_type'   => "JSAPI",
                    'openid'       => $openid,
                ];
                $order = WxPayApi::unifiedOrder($input, 6, $platform);
                $jsApiParameters = $tools->GetJsApiParameters($order);

                $debug = str_replace(',', "\n", $jsApiParameters);
                LOG::DEBUG('wxpay jsapi parameters:' . print_r($debug, 1));
                $data = ['paytype' => 0, 'jsApiParameters' => json_decode($jsApiParameters), 'orderid' => $orderid];
                return Api::make(Api::SUCCESS, $data);

            #　支付宝支付
            case PLATFORM_ALIPAY:
                $requsetParams = [
                    'body'             => $menu['subject'],
                    'subject'          => $menu['subject'],
                    'out_trade_no'     => $orderid,
                    'timeout_express'  => '10m', // 非必填项
                    'total_amount'     => $totalprice, // 单位元, 精确到小数点后两位
                    //"seller_id"      => ALIPAY_SELLER_ID, // 非必填项 收款支付宝用户ID。 如果该值为空，则默认为商户签约账号对应的支付宝用户ID
                    //"product_code" => 'QUICK_WAP_PAY',
                    //"goods_type" => 1, // 非必填项, 0 是虚拟物品, 1 是实物
                    'return_url' => '/index.php?mod=wechat&act=user&opt=pay&orderid='.$orderid .'#/afterPay', // 这个是公共请求参数里面的参数， 只是为了方便才放到这里来的
                ];

                //建立请求
                require_once JJSAN_DIR_PATH . 'lib/alipay/AlipayAPI.php';
                $formText = AlipayAPI::buildAlipaySubmitFormV2($requsetParams);
                $data = ['paytype' => 0, 'jsApiParameters' => $formText, 'orderid' => $orderid];
                return Api::make(Api::SUCCESS, $data);


            # 其他平台暂不支持
            default:
                return Api::make(6, [], '不支持其他平台');

        }

    }

    return Api::make(Api::ERROR_UNKNOWN);
}

function weappDecryptBizData($sessionKey, $encryptedData, $iv) {
    return wxAPI::weappDecryptBizData(WEAPP_APP_ID, $sessionKey, $encryptedData, $iv);
}

function weappGetSessionKey($code) {
    return wxAPI::weappGetSessionKey(WEAPP_APP_ID, WEAPP_APP_SECRET, $code);
}

function refundRequest($uid, $is_weapp = false)
{
    $userInfo    = ct('user')->fetch($uid);
    $totalRefund = $userInfo['usablemoney'];
    $openid      = $userInfo['openid'];
    $platform    = $userInfo['platform'];
    // 如果是微信小程序用户，替换openid和平台
    if ($is_weapp) {
        $openid   = ct('user_weapp')->getField($uid, 'openid');
        $platform = PLATFORM_WEAPP;
    }
    LOG::DEBUG('refund request, openid: ' . $openid . ', refund: ' . $totalRefund);
    if (empty($openid) || empty($totalRefund) || !is_numeric($totalRefund)) {
        LOG::ERROR('invalid parameter');
        return Api::make(2, [], '非法参数');
    }
    // 提现申请
    if (!$refundRequestRst = ct('user')->refundRequest($uid, $totalRefund)) {
        LOG::ERROR('no enough money');
        return Api::make(3, [], '提现失败');
    }
    // 记录用户流水
    ct('wallet_statement')->insert([
        'uid'        => $uid,
        'related_id' => $refundRequestRst['refund_log_id'], //提现记录id
        'type'       => WALLET_TYPE_REQUEST,
        'amount'     => $totalRefund,
        'time'       => date('Y-m-d H:i:s'),
    ]);

    $wxmsg = [
        'openid' => $openid,
        'request_time' => time(),
        'refund' => $totalRefund
    ];
    addMsgToQueue($platform, TEMPLATE_TYPE_WITHDRAW_APPLY, $wxmsg);

    LOG::DEBUG('refund success');
    return Api::make();
}

/**
 * 获取微信小程序模板需要使用的formid
 * @param $openid
 * @return bool | string  无可用id时返回false 有id时返回id
 */
function getFormIdByOpenid($openid) {
    $user = ct('user_weapp')->where(['openid' => $openid])->first();
    if (!$user || empty($user['form_ids'])) {
        return false;
    }
    // 检查form_id 是否在有效期内
    $formIds = $user['form_ids'];
    $formIds = json_decode($formIds, true);
    $form_id = false;
    foreach ($formIds as $k => $v) {
        // form_id 未过期符合要求
        if ($v['count'] >=1 && $v['timestamp'] + 7*24*3600 > time()) {
            $form_id = $v['form_id'];
            if ($v['count'] == 1) {
                unset($formIds[$k]);
                break;
            }
            $formIds[$k]['count'] -= 1;
            break;
        } else {
            unset($formIds[$k]);
        }
    }
    ct('user_weapp')->update($user['id'], ['form_ids' => $formIds ? json_encode(array_values($formIds), true) : '']);

    return $form_id;
}