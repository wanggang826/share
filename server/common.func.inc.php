<?php
// 二维码场景ID scene_id配置 (32位整数)
/*
 * 0~11位用于表示门店ID，取值范围0~4095，即系统最大可容纳4096家门店；
 * 12~18位用于表示座位ID，取值范围0~127，即每个门店最多有128个座位；
 * 19~31位用于表示点单序列，取值范围在0～8191，即座位订单序号，用于避免重复下单，
 * 序号可以循环累加，只需要保证短期内不重复即可；
 */
define( "SHOP_BIT_WIDTH",  12);
define( "SEAT_BIT_WIDTH",  7);
define( "SEQUENCE_BIT_WIDTH",  13);
define( "MAX_SEQ_NUMBER", 1<< SEQUENCE_BIT_WIDTH ); //循环点单序列最大值

/**
 * 编码二维码场景ID
 * @param 门店ID $shopId
 * @param 座位ID $seatId
 * @param 座位点单序列号 $seqNO
 * @return bool|number  若参数不是正整数,则返回false, 否则返回编码后的sceneId
 */
function encodeSceneId($shopId, $seatId, $seqNO) {
	if(!is_numeric($shopId) || !is_numeric($seatId) || !is_numeric($seqNO)
	|| $shopId < 0 || $seatId < 0 || $seqNO < 0) {
		return false;
	}

	// 二维码场景ID scene_id配置 (32位整数), $shopId+$seatId+$seqNO
	$sceneId = ($shopId << (SEAT_BIT_WIDTH + SEQUENCE_BIT_WIDTH))
	+ ($seatId << (SEQUENCE_BIT_WIDTH))
	+ $seqNO;
	LOG::DEBUG('encodeSceneId, sceneId:' . $sceneId . ', shopId:' . $shopId . ', seatId:' . $seatId . ', seqNO:'. $seqNO);

	return $sceneId;
}


/**
 * 解码二维码场景ID
 * @param 场景ID $sceneId
 * @return bool|array  若参数不是正整数,则返回false, 否则返回解码后的数据
 */
function decodeSceneId($sceneId) {
	if( !is_numeric($sceneId) || $sceneId < 0 ) {
		return false;
	}

	// 二维码场景ID scene_id配置 (32位整数), $shopId+$seatId+$seqNO
	$ret['shop_id'] = $sceneId >> (SEAT_BIT_WIDTH + SEQUENCE_BIT_WIDTH);
	$ret['seat_id'] = ($sceneId >> SEQUENCE_BIT_WIDTH) & ((1<<SEAT_BIT_WIDTH) - 1);
	$ret['seq_no'] = $sceneId & ((1<<SEQUENCE_BIT_WIDTH) - 1);

	LOG::DEBUG('decodeSceneId, sceneId:' . $sceneId . ', ret:' . print_r($ret, true));

	return $ret;
}

/**
 * 所有需要access_token的场景, 若返回的微信错误码为40001, 则表示access_token, 需强制向云服务器更新access_token
 * 有两种情况会发生重试:
 * 1. 云端服务器更新了access_token, 而本地服务器的access token未过期的情况
 * 2. 人为或其他非本系统原因更新了access token, 导致本地服务器访问微信接口返回过期错误,此时需向云端请求强制更新access token
 * 由于本地服务器的access token与云服务端的access_token的有效期几乎保持同步,所以这种重试情况发生概率很小
 * @param unknown $func 调用微信接口的函数名
 * @param array $args   函数参数
 * @return mixed        返回结果或错误码
 */
function callWeiXinFunc($func, array $args) {
	$ret = call_user_func_array($func, $args);
	$retryCount = WX_REQUEST_RETRY_COUNT? : 2;
	// 检查微信的错误码, 若是 access_token 过期,尝试重新向服务器请求access_token
	// 错误码: http://mp.weixin.qq.com/wiki/17/fa4e1434e57290788bde25603fa2fcbd.html
	while(in_array($ret['errcode'], array(40001, 40014, 41001, 42001)) && $retryCount > 0) {
		LOG::WARN("+++Retry, weixin access token out of date, force to update access token ret:" . print_r($ret, true));
		getAccessToken(true);
		$ret = call_user_func_array($func, $args);
		$retryCount--;
	}

	if($retryCount == 0) {
		LOG::ERROR("+++Try my best, but still failed,  ret:" . print_r($ret, true));
	}
	return $ret;
}

function callWeiXinFuncV2($func, array $args) {
	$at = getAccessToken();
	array_unshift($args, $at['access_token']);
	$ret = call_user_func_array($func, $args);
	$retryCount = WX_REQUEST_RETRY_COUNT? : 2;
	// 检查微信的错误码, 若是 access_token 过期,尝试重新向服务器请求access_token
	// 错误码: http://mp.weixin.qq.com/wiki/17/fa4e1434e57290788bde25603fa2fcbd.html
	while(in_array($ret['errcode'], array(40001, 40014, 41001, 42001)) && $retryCount > 0) {
		LOG::WARN("+++Retry, weixin access token out of date, force to update access token ret:" . print_r($ret, true));
		$at = getAccessToken(true);
		$args[0] = $at['access_token'];
		$ret = call_user_func_array($func, $args);
		$retryCount--;
	}

	if($retryCount == 0) {
		LOG::ERROR("+++Try my best, but still failed,  ret:" . print_r($ret, true));
	}
	return $ret;
}

function makeErrorData($errcode, $errmsg, $orderid = NULL, $ACK = NULL) {
	if(empty($ACK || $orderid)) {
		return ['errcode' => $errcode, 'errmsg' => $errmsg];
	} elseif($ACK == 'rent_confirm'){
		return ['ERRCODE' => $errcode, 'ERRMSG' => $errmsg, 'ORDERID' => $orderid, 'ACK' => $ACK];
	} else{
		return ['ERRCODE' => $errcode, 'ERRMSG' => $errmsg, 'ID' => $orderid, 'ACK' => $ACK];
	}
}

function getPages($total, $curpage, $nums, $baseurl, $lang = ['prev'=>"上一页",'next' => "下一页"], $showwindow = ''){
	$page['range'] = 2;
	$page['max'] = ceil($total / $nums);

	$pagehtm = '<ol class="paging">';

	$page['start'] = $curpage - $page['range'] > 0 ? $curpage - $page['range'] : 0 ;
	$page['end'] = $page['start']  + $page['range'] * 2 < $page['max'] ? $page['start']  + $page['range'] * 2 : $page['max'] - 1;
	$page['start'] = $page['end'] - $page['range'] * 2 > 0 ? $page['end'] - $page['range'] * 2 : 0;

	if($curpage > 0){
		$url = $baseurl."&page=$curpage";
		$pagehtm.= '<li class="prev"><a href="'.$url.'"'. $showwindow .'><em>&laquo;</em> '.$lang['prev'].'</a></li>';
	}

	if($page['start'] > 1){
		$url = $baseurl."&page=1";
		$pagehtm.= '<li><a href="'.$url.'"'. $showwindow .'>1</a></li>';
		$pagehtm.= '<li>...</li>';
	}elseif($page['start'] == 1){
		$url = $baseurl."&page=1";
		$pagehtm.= '<li><a href="'.$url.'"'. $showwindow .'>1</a></li>';
	}


	for($i = $page['start']; $i <= $page['end']; $i++){
		$url = $baseurl."&page=".($i + 1);
		if($curpage == $i){
			$pagehtm.= '<li class="current"><a href="'.$url.'"'. $showwindow .'>'.($i + 1).'</a></li>';
		}else{
			$pagehtm.= '<li><a href="'.$url.'"'. $showwindow .'>'.($i + 1).'</a></li>';
		}
	}

	if($page['end'] + 2 == $page['max']){
		$url = $baseurl."&page=".$page['max'];
		$pagehtm.= '<li><a href="'.$url.'"'. $showwindow .'>'.$page['max'].'</a></li>';
	}elseif($page['end'] + 2 < $page['max']){
		$url = $baseurl."&page=".$page['max'];
		$pagehtm.= '<li>...</li>';
		$pagehtm.= '<li><a href="'.$url.'"'. $showwindow .'>'.$page['max'].'</a></li>';
	}

	if($curpage + 1 < $page['max']){
		$url = $baseurl."&page=".($curpage + 2);
		$pagehtm.= '<li class="next"><a href="'.$url.'"'. $showwindow .'>'.$lang['next'].' <em>&raquo;</em></a></li>';
	}

	$pagehtm.= '</ol>';

	return $pagehtm;
}

function redirect($message, $forward = '', $values = array(), $wait = 3) {
    $vars = explode(':', $message);
    if (count($vars) == 2) {
        $show_message = lang('plugin/'.$vars[0], $vars[1], $values);
    } else {
        $show_message = lang('message', $message, $values);
    }
    // 跳转url, 3s后就调转到目标url, 没有的话就跳转到跳转回上一个页面
    if ($forward) {
        $show_message .= "<script>(function(){var time = $wait;
                            var interval = setInterval(function(){
                                time--;
                                if(time == 0) {
                                    location.href = '$forward';
                                    clearInterval(interval);
                                };
                            }, 1000);
                            })();
                            </script>";
    } else {
        $show_message .= "
                        <script>
                        (function(){
                        var time = $wait;
                        var interval = setInterval(function(){
                            time--;
                            if(time == 0) {
                                location.href = window.history.go(-1);
                                clearInterval(interval);
                            };
                        }, 1000);
                        })();
                        </script>";
    }
    include template(PLUGIN_NAME . ':common/showmessage');
    exit;
}

// 二维数组排序
function multi_array_sort(array $arrays, $sort_key, $sort_order = SORT_ASC, $sort_type = SORT_NUMERIC) {
    foreach ($arrays as $array) {
        if (is_array($array)) {
            $key_arrays[] = $array[$sort_key];
        } else {
            return false;
        }
    }
    array_multisort($key_arrays, $sort_order, $sort_type, $arrays);
    return $arrays;
}
