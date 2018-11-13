<?php
use model\Api;

$opt = $opt ? : 'global_settings';

switch ($opt) {

	// 全局同步配置策略 系统设置
	case 'system_settings':
		if($do == 'set') {
			$settings = array();
			$settings['domain'] = $_GET['domain'];
			$settings['ip'] = $_GET['ip'];
			$settings['port'] = $_GET['port'];
			$settings['checkupdatedelay'] = $_GET['checkupdatedelay'];
			$settings['heartbeat'] = $_GET['heartbeat'];
			$settings['soft_ver'] = $_GET['soft_ver'];
			$settings['file_name'] = $_GET['file_name'];
			$res = $admin->system_settings($settings);
            if($res){
                Api::output([], 0, '全局同步策略更新成功');
            }else{
                Api::output([], 1, '全局同步策略更新失败');
            }
            exit;
		}
		$systemSettings = json_decode( C::t('common_setting')->fetch('jjsan_system_settings'), true );
		break;

		// 同步配置策略列表
	case 'station_settings_strategy':
		// 删除同步配置策略操作
		if(isset($do)){
		    switch ($do) {
                case 'add':
                    if ($_POST) {
                        $settings = array();
                        $settings['checkupdatedelay'] = isset($_GET['checkupdatedelay']) ? $_GET['checkupdatedelay'] : '';
                        $settings['domain'] = isset($_GET['domain']) ? $_GET['domain'] : '';
                        $settings['ip'] = isset($_GET['ip']) ? $_GET['ip'] : '';
                        $settings['port'] = isset($_GET['port']) ? $_GET['port'] : '';
                        $settings['file_name'] = isset($_GET['file_name']) ? $_GET['file_name'] : '';
                        $settings['heartbeat'] = isset($_GET['heartbeat']) ? $_GET['heartbeat'] : '';
                        $settings['soft_ver'] = isset($_GET['soft_ver']) ? $_GET['soft_ver'] : '';
                        $name = $_GET['name'];
                        $res = $admin->station_settings_add($settings, $name);
                        if($res){
                            Api::output([], 0, '添加成功');
                        }else{
                            Api::output([], 1, '添加失败');
                        }
                        exit;
                    }
                    include template('jjsan:cp/settings/strategy_add');
                    break;

                case 'edit':
                    if ($_POST) {
                        $station_strategy_id = $_GET['station_strategy_id'];
                        $settings = array();
                        $settings['checkupdatedelay'] = isset($_GET['checkupdatedelay']) ? $_GET['checkupdatedelay'] : '';
                        $settings['domain'] = isset($_GET['domain']) ? $_GET['domain'] : '';
                        $settings['ip'] = isset($_GET['ip']) ? $_GET['ip'] : '';
                        $settings['port'] = isset($_GET['port']) ? $_GET['port'] : '';
                        $settings['file_name'] = isset($_GET['file_name']) ? $_GET['file_name'] : '';
                        $settings['heartbeat'] = isset($_GET['heartbeat']) ? $_GET['heartbeat'] : '';
                        $settings['soft_ver'] = isset($_GET['soft_ver']) ? $_GET['soft_ver'] : '';
                        $name = $_GET['name'];
                        $res = $admin->station_settings_edit($station_strategy_id, $settings, $name);
                        if($res){
                            Api::output([], 0, '同步配置策略更新成功');
                        }else{
                            Api::output([], 1, '同步配置策略更新失败');
                        }
                        exit;
                    }
                    // show setting detail

                    $res = ct('station_settings') -> fetch($station_strategy_id);
                    if(isset($res['settings'])){
                        $systemSettings = json_decode($res['settings'],true);
                        $systemSettings['name'] = $res['name'];
                    }
                    include template('jjsan:cp/settings/strategy_add');
                    break;

                case 'delete':
                    // 如果没有设备使用此配置　就直接删除配置　
                    // 如果有　则提醒用户哪些站点使用了此配置　必须先更改配置　才能删除
                    if ($res = ct('station')->where(['station_setting_id' => $station_strategy_id])->limit(10)->get()) {
                        $name = array_map(function($a){
                            return $a['title'];
                        }, $res);
                        Api::output([], 1, '删除失败:'.implode(',', $name).'等正在使用这个策略');
                    } else {
                        $res = $admin->station_settings_delete($station_strategy_id);
                        if ($res) {
                            Api::output([], 0, '删除成功');
                        } else {
                            Api::output([], 1, '删除失败');
                        }
                    }
                    break;

            }
            exit;
		}
		// show station setting list
		$where = ['status' => 0];
		$all_settings = ct('station_settings')
            ->where($where)
            ->limit(($page - 1) * RECORD_LIMIT_PER_PAGE, RECORD_LIMIT_PER_PAGE)
            ->get();
		$count = ct('station_settings')->where($where)->count();
        unset($_GET['page']);
        $pagehtm = getPages($count, $page - 1, RECORD_LIMIT_PER_PAGE, 'index.php?'.http_build_query($_GET));
		break;

	case 'fee_settings':
		if($do == 'strategy') {
            $settings = array();
            $settings['fixed_time']     = $fixed_time;
            $settings['fixed_unit']     = $fixed_unit;
            $settings['fixed']          = $fixed;
            $settings['fee_time']       = $fee_time;
            $settings['fee']            = $fee;
            $settings['fee_unit']       = $fee_unit;
            $settings['max_fee_time']   = $max_fee_time;
            $settings['max_fee_unit']   = $max_fee_unit;
            $settings['max_fee']        = $max_fee;
            $settings['free_time']      = $free_time;
            $settings['free_unit']      = $free_unit;
            $res = $admin->fee_settings($settings);
            if ($res) {
                Api::output([], 0, '全局收费策略更新成功');
            } else {
                Api::output([], 1, '全局收费策略更新失败');
            }
            exit;
		}
		$feeSettings = C::t('common_setting')->fetch('jjsan_fee_settings');
        $feeSettings = json_decode( $feeSettings, 1 );
		break;

	case 'wechat_settings':

		if ($_GET['wechat']) {
			$res = C::t('common_setting')->update('jjsan_wechat_replyMsg', json_encode($_GET['replymsg'])) && C::t('common_setting')->update('jjsan_wechat_defaultMsg', json_encode($_GET['defaultMsg'])) && C::t('common_setting')->update('jjsan_wechat_subscribeMsg', json_encode($_GET['subscribeMsg']));
			if (!$res) {
				LOG::ERROR('update wechat replymsg fail');
			}
		} elseif ($_GET['func']) {
			$keywords = json_decode( C::t('common_setting')->fetch('jjsan_wechat_keywords'), true );
			$row      = $_GET['row'];
			$num      = $_GET['num'];
			$keyword  = $_GET['keyword'];
			$func     = $_GET['func'];
			if ($func == 'add') {
				$keywords[$row][] = '';
			} elseif ($func == 'delete') {
				unset($keywords[$row][$num]);
			} elseif ($func == 'edit') {
				$keywords[$row][$num] = $keyword;
			} elseif ($func == 'add_new_rule') {
				$keywords[] = array();
			} elseif ($func == 'delete_rule') {
				unset($keywords[$row]);
			}
			if (! C::t('common_setting')->update('jjsan_wechat_keywords', json_encode($keywords))) {
				LOG::ERROR('update wechat keyword fail');
			} else {
				echo "success";
			}
			exit;
		}
		$keywords = json_decode( C::t('common_setting')->fetch('jjsan_wechat_keywords'), true );
		$replyMsg = json_decode( C::t('common_setting')->fetch('jjsan_wechat_replyMsg'), true );
		$defaultMsg = json_decode( C::t('common_setting')->fetch('jjsan_wechat_defaultMsg'), true );
		$subscribeMsg = json_decode( C::t('common_setting')->fetch('jjsan_wechat_subscribeMsg'), true );
		break;

    case 'wechat_pictext':
        if (isset($do)) {
            switch ($do) {
                case 'add':
                case 'edit':
                    if ($_POST) {
                        $settings = array();
                        $settings['pictext']['title']         = $title;
                        $settings['pictext']['wechat_picurl'] = $wechat_picurl;
                        $settings['pictext']['alipay_picurl'] = $alipay_picurl;
                        $settings['pictext']['url']           = $url;
                        $settings['stime']                    = strtotime($stime);
                        $settings['etime']                    = strtotime($etime);
                        if($pid){
                            $res = $admin->pictext_settings_edit($settings, $pid, $name);
                        } else {
                            $res = $admin->pictext_settings_add($settings, $name);
                        }
                        if ($res) {
                            Api::output([], 0, '微信图文消息设置成功');
                        } else {
                            Api::output([], 1, '微信图文消息设置失败');
                        }
                        exit;
                    }
                    $pictext = ct('pictext_settings')->fetch($pid);
                    if($pictext){
                        $pictext['pictext'] = json_decode($pictext['pictext'], true);
                        $pictext['stime'] = date('Y-m-d H:i:s', $pictext['stime']);
                        $pictext['etime'] = date('Y-m-d H:i:s', $pictext['etime']);
                    }
                    include template('jjsan:cp/settings/wechat_pictext_edit');
                    break;
                case 'delete':
                    // 如果没有设备使用此配置　就直接删除配置　
                    // 如果有　则提醒用户哪些站点使用了此配置　必须先更改配置　才能删除
                    if ($res = ct('shop_station')->where(['pictext_settings' => $pid])->limit(3)->get()) {
                        $name = array_map(function ($a) {
                            return $a['title'];
                        }, $res);
                        Api::output([], 1, '删除失败:' . implode(',', $name) . '等正在使用该图文配置');
                    } else {
                        $res = $admin->pictext_settings_delete($pid);
                        if ($res) {
                            Api::output([], 0, '删除成功');
                        } else {
                            Api::output([], 1, '删除失败');
                        }
                    }
                    exit;
                    break;
            }
            exit;
        }
        $res = ct('pictext_settings')->order('id desc')->get();
        $pictexts = array_map(function($a){
            $tmp = ct('shop_station')->where(['pictext_settings' => $a['id']])->get();
            $a['shops'] = $tmp;
            return $a;
        }, $res);
        break;

	case 'global_settings':
		if($_GET['submit']){
			//$settings['service_phone'] = $_GET['service_phone'];
            //$res = $admin->global_settings($settings);
            if ($res) {
                Api::output([], 0, '客服电话更新成功');
            } else {
                Api::output([], 1, '客服电话更新失败');
            }
            exit;
		}
		break;

    case 'local_fee_settings':
        if (isset($do)) {
            switch ($do) {
                case 'add':
                case 'edit':
                    if ($_POST) {
                        $settings = array();
                        $settings['fixed_time']     = $fixed_time;
                        $settings['fixed_unit']     = $fixed_unit;
                        $settings['fixed']          = $fixed;
                        $settings['fee_time']       = $fee_time;
                        $settings['fee']            = $fee;
                        $settings['fee_unit']       = $fee_unit;
                        $settings['max_fee_time']   = $max_fee_time;
                        $settings['max_fee_unit']   = $max_fee_unit;
                        $settings['max_fee']        = $max_fee;
                        $settings['free_time']      = $free_time;
                        $settings['free_unit']      = $free_unit;
                        if ($fid) {
                            $res = $admin->local_fee_settings_edit($settings, $fid, $name);
                        } else {
                            $res = $admin->local_fee_settings_add($settings, $name);
                        }
                        if ($res) {
                            Api::output([], 0, '更新成功');
                        } else {
                            Api::output([], 1, '更新失败');
                        }
                        exit;
                    }
                    $fee = ct('fee_strategy')->fetch($fid);
                    $fee['fee'] = json_decode($fee['fee'], true);
                    include template('jjsan:cp/settings/local_fee_settings_edit');
                    break;
                case 'delete':
                    // 如果没有设备使用此配置　就直接删除配置　
                    // 如果有　则提醒用户哪些站点使用了此配置　必须先更改配置　才能删除
                    if ($res = ct('shop_station')->where(['fee_settings' => $fid])->limit(3)->get()) {
                        $name = array_map(function($a){
                            return $a['title'];
                        }, $res);
                        Api::output([], 1, '删除失败:'.implode(',', $name).'等正在使用这个策略');
                    } else {
                        $res = $admin->local_fee_settings_delete($fid);
                        if ($res) {
                            Api::output([], 0, '删除成功');
                        } else {
                            Api::output([], 1, '删除失败');
                        }
                    }
                    exit;
                    break;
            }
            exit;
        }
        $res = ct('fee_strategy')->order('id desc')->get();
        $fees = array_map(function($a){
            $fid = $a['id'];
            $tmp = ct('shop_station')->where(['fee_settings' => $fid])->get();
            $a['shops'] = $tmp;
            return $a;
        }, $res);
        break;
	default:
		break;
}
