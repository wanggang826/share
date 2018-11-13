<?php
use model\Api;

switch ( $opt ) {
	
	case 'getmatbody':		// Can move to JavaScript later.
		$simple = $_GET['simple'] ? : 0 ;
		$matid = $_GET['matid'];
		$src = $_GET['src'];
		$name = $_GET['name'];
		$type = $_GET['type'];
		include template('jjsan:common/mat_body');

		exit;

	// 素材列表
	case 'getmatlist':
		$num =  C::t('forum_attachment')->count_by_tid(1);
		$maxPage = ceil( $num / RECORD_LIMIT_PER_PAGE );
		$page = $_GET['cur'] ? : 1 ;
		if ( $_GET['go'] == 'prev' ) {
			$page--;
		}elseif ( $_GET['go'] == 'next' ) {
			$page++;
			$page = $page > $maxPage? $maxPage : $page;
		}
		$start = ( $page - 1 ) * RECORD_LIMIT_PER_PAGE;
		foreach ( DB::fetch_all('SELECT * FROM %t WHERE %i %i'.DB::limit($start, RECORD_LIMIT_PER_PAGE), array('forum_attachment', DB::field('tid', 1).' AND '.DB::field('pid', 1), 'ORDER BY '.DB::order('aid', 'DESC'))) as $value) {
			$attach = C::t('forum_attachment_n')->fetch($value['tableid'], $value['aid']);
			$mats[$value['aid']] = array(
					'fn' => $attach['filename'],
					'path' => ATTACHPATH.$attach['attachment'],
			);
		}
		$simple = $_GET['simple'] ? : 0 ;
		$hasNext = $page < $maxPage; // 是否显示上一页
		$hasPre = $page > 1;// 是否显示下一页
		include template('jjsan:common/mat_select');
		exit;
	
		
	// 上传单个文件
	case 'file-upload':
		$action = isset($_GET['action']) ? $_GET['action']:null;
		if($action){
			include template('jjsan:cp/common/file_upload');
		}else{
			include template('jjsan:cp/common/file_upload_error');
		}
		exit;
	
	// 选择列表
	case "material-update":
		$action = isset($_GET['action']) ? $_GET['action']:null;
		$imgs = isset($_GET['imgs']) ? json_decode($_GET['imgs']):null;
		if($action){
			include template('jjsan:cp/common/material_update');
		}else{
			include template('jjsan:cp/common/material_update_error');
		}
		exit;

    // 省市区
    case "get_area_info":
        // 全局搜索
        if ($auth->globalSearch) {
            if ($ajax == 1) {
                if($province) {
                    if($city) {
                        Api::output(getAreasByCity($province, $city, $area_nav_tree));
                    } else {
                        Api::output(getCitiesByProvince($province, $area_nav_tree));
                    }
                }
                exit;
            }

            if ($ajax == 2) {
                // 所有省份
                $provinces = array_map(function($v){
                    return $v['province'];
                }, $area_nav_tree);
                Api::output($provinces);
                exit;
            }
        }

        // 非全局搜索(仅授权的省市区)
        if (!$auth->globalSearch) {
            $adminId = $admin->adminInfo['id'];
            // 通过授权的城市获取相应的shop id
            $cities = ct('admin_city')->getAccessCities($adminId);
            $cShops = ct('shop')->where(['city' => $cities])->get();
            $cShops = array_map(function($a){
                return $a['id'];
            }, $cShops);

            // 授权的shop id
            $shops = ct('admin_shop')->where(['admin_id' => $adminId, 'status' => table_jjsan_admin_shop::STATUS_PASS])->get();
            $shops = array_map(function($a){
                return $a['shop_id'];
            }, $shops);

            // 合并去重
            $shopIds = array_merge($cShops, $shops);
            $shopIds = array_unique($shopIds);

            // 组合省市区,去重
            $infos = ct('shop')->select('province, city, area')->where(['id' => $shopIds])->get();
            $infos = array_map(function($a){
                return join(',', $a);
            }, $infos);
            $infos = array_unique($infos);
            foreach ($infos as $v) {
                $tmp[] = explode(',', $v);
            }
            $infos = $tmp;

            if ($ajax == 1) {
                if($province) {
                    if($city) {
                        // 省市下面的区
                        $areas = array_map(function($a) use ($province, $city) {
                            if ($a[0] == $province && $a[1] == $city) {
                                return $a[2];
                            }
                            return '';
                        }, $infos);
                        $areas = array_filter($areas);
                        $areas = array_unique($areas);
                        Api::output($areas);
                        exit;
                    } else {
                        // 省下面的市
                        $cities = array_map(function($a) use ($province) {
                            if ($a[0] == $province) {
                                return $a[1];
                            }
                            return '';
                        }, $infos);
                        $cities = array_filter($cities);
                        $cities = array_unique($cities);
                        Api::output($cities);
                        exit;
                    }
                }
                exit;
            }

            if ($ajax == 2) {
                // 所有省
                $provinces = array_map(function($a){
                    return $a[0];
                }, $infos);
                $provinces = array_unique($provinces);
                Api::output($provinces);
                exit;
            }
        }
        break;
			
	default:
		# code...
		break;
}