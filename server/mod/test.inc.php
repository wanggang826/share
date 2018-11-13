<?php
$stationid = $stationid ? : 999;
switch ($opt) {

    // 获取微信二维码
    case 'get_qrcode' :
        $ret = DB::result_first('SELECT count(*) FROM %t', ['jjsan_station']);
        if(! $ret) {
            echo 'no station now, please add station first';
            exit;
        }
        LOG::DEBUG('generate test qrcode sid:' . $ret);
        dheader('location: ' . callWeiXinFunc('getQrcodeUrl', array($stationid)));
        break;

    // 获取支付宝二维码
    case 'get_qrcode_ali' :
        $ret = DB::result_first('SELECT count(*) FROM %t', array('jjsan_station'));
        if(! $ret) {
            echo 'no station now, please add station first';
            exit;
        }
        LOG::DEBUG('generate test qrcode sid:' . $ret);
        require_once JJSAN_DIR_PATH . '/lib/alipay/AlipayAPI.php';
        dheader('location: ' . AlipayAPI::createQrcode($stationid));
        break;

    // 创建事件菜单
    case 'create_menu' :
        $menu = array(
            'button' => array(
                array(
                    'action_param' => 'alipays://platformapi/startapp?appId=10000007',
                    'action_type' => 'link',
                    'name' => '租借雨伞',
                ),
                array(
                    'action_param' => 'http://jjs.lystrong.cn/index.php?mod=wechat&act=shop&opt=list',
                    'action_type' => 'link',
                    'name' => '附近网点',
                ),
                array(
                    'name' => '押金提现',
                    'subButton' => array(
                        array(
                            'action_param' => 'http://jjs.lystrong.cn/index.php?mod=wechat&act=user&opt=carry_cash',
                            'action_type' => 'link',
                            'name' => '押金提现',
                        ),
                        array(
                            'action_param' => 'http://jjs.lystrong.cn/index.php?mod=wechat&act=user&opt=ucenter',
                            'action_type' => 'link',
                            'name' => '用户中心',
                        ),
                    )
                )
            )
        );
        LOG::DEBUG('create menu :' . print_r($menu, 1));
        require_once JJSAN_DIR_PATH . '/lib/alipay/AlipayAPI.php';
        echo AlipayAPI::createMenu($menu);
        break;

    // 更新事件菜单
    case 'update_menu' :
        $menu = array(
            'button' => array(
                array(
                    'action_param' => 'http://jjs.lystrong.cn/index.php?mod=wechat&act=user&opt=center#/oneKeyUse',
                    'action_type' => 'link',
                    'name' => '租借雨伞',
                ),
                array(
                    'action_param' => 'http://jjs.lystrong.cn/index.php?mod=wechat&act=user&opt=center#/map',
                    'action_type' => 'link',
                    'name' => '附近网点',
                ),
                array(
                    'action_param' => 'http://jjs.lystrong.cn/index.php?mod=wechat&act=user&opt=center',
                    'action_type' => 'link',
                    'name' => '用户中心',
                ),
            )
        );
        LOG::DEBUG('update menu :' . print_r($menu, 1));
        require_once JJSAN_DIR_PATH . '/lib/alipay/AlipayAPI.php';
        echo AlipayAPI::updateMenu($menu);
        break;

    case 'zhima':
        $params = array(
            'order_no'          => '3231884',
            'product_code'      => 'w1010100000000002858',
            'restore_time'      => date('Y-m-d H:i:s', $order['return_time']? : time()),
            'pay_amount_type'   => 'RENT',
            'pay_amount'        => 0,
            'restore_shop_name' => '狂拽酷炫吊炸天',
        );
        require_once JJSAN_DIR_PATH . '/lib/alipay/AlipayAPI.php';
        $resp = AlipayAPI::zhimaOrderRentComplete($params);
        var_dump($resp);
        break;

    case 'get_user_info':
        echo '<pre>';
        LOG::DEBUG('this is the test page');
        $access_token = getAccessToken()['access_token'];
        print_r(wxAPI::getUserInfo($access_token, 'owROB1dfJ4Lf4Iw35fnP-24o4H2s'));
        echo '</pre>';
        break;
		
	case 'down':
        include template('jjsan:common/system_error');
        exit;

    case 'add_session':
        $uid = $uid ? : 1;
        $s = md5($uid.time().mt_rand(100000, 999999));
        setcookie('session', $s, time()+24*3600);
        DB::insert('jjsan_user_session', [
            'uid' => $uid,
            'session' => $s,
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s')
        ]);
        echo <<<EOF
<html>
<h1>设置成功！</h1>
<script>
localStorage.setItem('session', "$s")
</script>
</html>
EOF;
        break;
}