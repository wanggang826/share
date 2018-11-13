<?php

use model\Api;
use model\User;
use model\Trade;

/************************自定义全局变量*********************/
$platform_arr    = [ 0 => '微信',1 => '支付宝',2 => '全部']; // 支付平台数组
$status_arr 	 = [0 => '全部',1 => '借出中',2 => '已归还']; // 租借状态
$page_size       = RECORD_LIMIT_PER_PAGE; //页面大小
$count           = 0; // 总记录条数
$url             = "/index.php?mod=$mod&act=$act";
//$title_list      = getStationCity(); // 站点所在城市列表
$show_days       = 7; // 默认显示一周的数据
$oneday          = 24 * 60 * 60; // 一天转换成秒数
$today           = strtotime(date('Y-m-d',time())) + $oneday - 1;// 当天时间 :  23 : 59 : 59

/************************用户输入***************************/
$role_selected   = isset($role_selected) ? $role_selected : -1; // 默认全部角色用户
$platform        = isset($platform) ? $platform : 2;  // 默认平台　全部
$nickname        = isset($nickname) ? trim($nickname) : '';
$id              = isset($id) ? $id : '';
$city            = isset($city) ? $city : '';
$etime           = !empty($etime) ? strtotime(date('Y-m-d',strtotime($etime))) + $oneday - 1: $today; // 结束日期
$stime           = !empty($stime) ? strtotime(date('Y-m-d',strtotime($stime)))  : ($etime - $show_days * $oneday + 1);  // 起始日期

/********************** 使用到的模型 ***********************/
$user            = new User();  // 用户模型
$jjsan_user        = ct('user');  // 用户表对象
$jjsan_station	 = ct('station'); // 站点对象
$jjsan_tradelog    = ct('tradelog'); // 订单对象
$user_data_cache = ct('user_statistics_cache'); // 用户分析数据缓存对象

switch($opt){

	// 用户列表
	case "list":
		$res = $jjsan_user -> search($openid,$nickname,$platform,$status,$_GET['stime'],$_GET['etime'],$page,$page_size,$role_selected,$id); // 查询用户
		if($res){
			$users = $res['data'];
			$count = $res['count'];
			if($users){
				// 为用户列表添加相关数据
				$trade = new Trade();
				foreach ($users as &$u) {
					$u['order_count'] 				= $trade -> order_count_by_uid($u['id']);
					$u['usefee_count'] 				= $trade -> usefee_count_by_uid($u['id']);
					$u['outstanding_order_count'] 	= $trade -> outstanding_order_count($u['id']);
					$u['role']						= $u['role_id'] ? $jjsan_user_role -> fetch_all($u['role_id'])[$u['role_id']]['role'] : "普通用户";
				}
			}else{
				$no_users = true;
			}
		}
		unset($_GET['page']);
		$pagehtm = getPages($count, $page - 1,$page_size,"index.php?".http_build_query($_GET));
		break;

	// 某用户提现列表
	case 'refund_list':
		$orderby = DB::order( 'request_time', 'DESC' );
		$page = $_GET['page'] ? : 1 ;
		$gtype = $_GET['gtype'];
		$gvalue = $_GET['gvalue'];
		$start = ( $page - 1 ) * RECORD_LIMIT_PER_PAGE;
		if ($gtype == 'nickname') {
            $gvalue = json_encode($gvalue);
            $gvalue = substr_replace($gvalue, '', 0, 1);
            $gvalue = substr_replace($gvalue, '', -1, 1);
            $gvalue = preg_replace('/u/','\\\\\\\\\\u',$gvalue);
            $gvalue = preg_replace('/\"/','\\\\\\\\\\"',$gvalue);
            $gvalue = str_replace('\'','\\\'',$gvalue);
            $nicknameCond = 1;
		} elseif ($gtype == 'openid') {
			$openidCond = DB::field('openid', $gvalue);
		}

		if ($openidCond) {
		    if($request){
			    $where .= " AND t.openid = '$gvalue' AND a.status = " . REFUND_STATUS_REQUEST;
            }else{
		        $where .= " AND t.openid = '$gvalue'";
            }
			$orderby = " ORDER BY a.request_time DESC";
			$sqlfrom = " INNER JOIN `".DB::table('jjsan_user_info')."` t ON t.id=a.uid" ;
			$refund_list = DB::fetch_all('SELECT a.* FROM '.DB::table('jjsan_refund_log'). " a ".$sqlfrom .$where . $orderby . DB::limit($start, RECORD_LIMIT_PER_PAGE));
			$ret = DB::fetch_first('SELECT count(*) as count,uid FROM '.DB::table('jjsan_refund_log'). " a ".$sqlfrom .$where);
		} elseif ($nicknameCond) {
		    if($request){
			    $where .= " AND t.nickname LIKE '%$gvalue%' AND a.status = " . REFUND_STATUS_REQUEST;
            }else{
		        $where .= " AND t.nickname LIKE '%$gvalue%'";
            }
			$orderby = " ORDER BY a.request_time DESC";
			$sqlfrom = " INNER JOIN `".DB::table('jjsan_user_info')."` t ON t.id=a.uid" ;
			$refund_list = DB::fetch_all('SELECT a.* FROM '.DB::table('jjsan_refund_log'). " a ".$sqlfrom .$where . $orderby . DB::limit($start, RECORD_LIMIT_PER_PAGE));
			$ret = DB::fetch_first('SELECT count(*) as count,uid FROM '.DB::table('jjsan_refund_log'). " a ".$sqlfrom .$where);
		} else {
			$refund_list = array();
		}

		$num = $ret['count'];
		$uid = $ret['uid'];

		foreach ($refund_list as $key => &$value) {
		    $value['request_time'] = date("Y-m-d H:i:s", $value['request_time']);
		    if ($value['refund_time'] == 0) {
		        $value['refund_time']  = '暂未退款';
		    } else {
		        $value['refund_time']  = date("Y-m-d H:i:s", $value['refund_time']);
		    }
		    $value['detail_count'] = count(json_decode($value['detail'], true));
		}
		$url = $url. '&opt=refund_list&gtype=' . $_GET['gtype'] . '&gvalue=' . $_GET['gvalue'];
		$pagehtm = getPages($num, $page - 1,
		    RECORD_LIMIT_PER_PAGE, $url,
		    array('prev' => '上一页','next' => '下一页')
		);
		$user = ct('user')->getPlatformInfo($uid);
		$user['nickname'] = json_decode(ct('user_info')->getField($uid, 'nickname'));
		break;

	// 退款来源订单数列表弹窗
	case 'show_refund_detail':
		$page = $page ? : 1 ;
		$start = ($page - 1) * 5 ;
		$orderby = DB::order( 'request_time', 'DESC' );
		$idCond = DB::field('id', $id);

        $refund_detail = ct('refund_log')->select('detail')->where(['id' => $id])->first();
        $refund_detail = $refund_detail['detail'];
        $refund_detail = preg_replace('/[\[\]"]/', '', $refund_detail);
        $result = explode(',', $refund_detail);
        $refund_detail = [];
        for($i = 0; $i < count($result); $i += 2){
            $refund_detail[] = [$result[$i], number_format($result[$i+1], 2, '.', '')];
        }
		$num = count($refund_detail);
		$refund_detail = array_slice($refund_detail, $start, 5);
		$url = $url . "&opt=show_refund_detail&id=$id";
		$pagehtm = getPages($num, $page - 1, 5, $url,
            array('prev' => '上一页','next' => '下一页'),
		    ' onclick="showWindow(\'refunddetail\', this.href);"'
		);
		include template('jjsan:cp/user/refund_detail');
		exit;
		break;

	// 该用户所有订单弹窗
	case 'buyer_order':
		$uid = $_GET['buyer'];
		$num = $_GET['count'];
		$page = $_GET['page'] ? : 1 ;
		$start = ( $page - 1 ) * 5;
		$orders = DB::fetch_all('SELECT * FROM %t WHERE %i' . DB::limit($start, 5), array(
			'jjsan_tradelog',
			DB::field('uid', $uid)));
		$url = "index.php?mod=cp&act=user&opt=buyer_order&count=$num&buyer=$uid";
        $pagehtm = getPages($num, $page - 1, 5, $url);
        include template('jjsan:cp/user/buyer_order');
        exit;
		break;

    case 'zero_fee_user_list':
        if (isset($do)) {
            switch ($do) {
                case 'add':
                    if ($_POST) {
                        $user = ct('user')->where(['openid' => $openid])->first();
                        if (empty($user)) {
                            Api::output([], 1, '用户不存在');
                            exit;
                        }
                        $zeroFeeUserList = ct('common_setting')->fetch('zero_fee_user_list');
                        if (empty($zeroFeeUserList)) {
                            ct('common_setting')->insert(['skey' => 'zero_fee_user_list', 'svalue' => json_encode([$openid])]);
                        } else {
                            $zeroFeeUserList = json_decode($zeroFeeUserList['svalue'], true);
                            if (in_array($openid, $zeroFeeUserList)) {
                                Api::output([], 1, 'openid重复，添加失败');
                                exit;
                            }
                            $zeroFeeUserList[] = $openid;
                            ct('common_setting')->update('zero_fee_user_list', ['svalue' => json_encode($zeroFeeUserList)]);
                        }
                        $admin->log_add_zero_fee_user($openid);
                        Api::output([], 0, '添加成功');
                        exit;
                    }
                    include template('jjsan:cp/user/zero_fee_user_list_add');
                    exit;
                    break;

                case 'delete':
                    if ($_POST) {
                        $user = ct('user')->where(['openid' => $openid])->first();
                        if (empty($user)) {
                            Api::output([], 1, '用户不存在');
                            exit;
                        }
                        $zeroFeeUserList = ct('common_setting')->fetch('zero_fee_user_list');
                        $zeroFeeUserList = json_decode($zeroFeeUserList['svalue'], true);
                        $key = array_search($openid, $zeroFeeUserList);
                        if ($key === false) {
                            Api::output([], 1, 'openid不是零收费用户');
                            exit;
                        }
                        array_splice($zeroFeeUserList, $key, 1);
                        ct('common_setting')->update('zero_fee_user_list', ['svalue' => json_encode($zeroFeeUserList)]);
                        $admin->log_delete_zero_fee_user($openid);
                        Api::output([], 0, 'openid移除成功');
                        exit;
                    }
                    break;
            }
        }
        $users = ct('common_setting')->fetch('zero_fee_user_list');
        if ($users) {
            $userIds = json_decode($users['svalue'], true);
            $userInfo = [];
            foreach($userIds as $v) {
                $tmp = [];
                $info = ct('user_info')->where(['openid' => $v])->first();
                $tmp['openid'] = $v;
                if ($info['nickname']) {
                    $tmp['nickname'] = $info['nickname'];
                }
                $userInfo[] = $tmp;
            }
        }
        break;

	// 默认
	default:
		header("location:index.php?mod=$mod&act=$act&opt=list");
		exit;

}
