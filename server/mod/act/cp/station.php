<?php

use model\Station;
use model\Shop;
use model\Api;

require_once JJSAN_DIR_PATH . '/lib/swapi.class.php';
$opt     = $opt ?: 'list';
$url     = "index.php?mod=$mod&act=$act&opt=$opt";
$shop    = new Shop();
$station = new Station();
switch ($opt)
{

    // 站点状态列表
    case 'list':
        if (isset($do))
        {
            if (!$auth->globalSearch && !$auth->checkStationIdIsAuthorized($sid))
            {
                echo 'unauthorized station';
                exit;
            }
            switch ($do)
            {
                // 槽位操作
                case 'slot_action':
                    $slotsStatus = ct('station')->getSlotsStatus($sid);
                    $slotscount  = count($slotsStatus);
                    for ($i = 1; $i <= $slotscount; $i++)
                    {
                        $rst = ct('station_slot_log')->getLastSyncTime($sid, $i, 3);
                        if (empty($rst) || !$rst['last_sync_umbrella_time'])
                        {
                            $lastSyncTime[$i] = '无';
                        } else
                        {
                            $lastSyncTime[$i] = date('Y-m-d H:i:s', $rst['last_sync_umbrella_time']);
                        }
                    }
                    include template('jjsan:cp/station/slot_action');
                    exit;
                    break;
                case 'manually_control':
                    $station = ct('station');
                    include template('jjsan:cp/station/manually_control');
                    exit;
                    break;
                case 'slotLock':
                    if (!$auth->globalSearch && !$auth->checkStationIdIsAuthorized($sid))
                    {
                        echo 'unauthorized station';
                        exit;
                    }
                    swAPI::slotLock($sid, $slot_num, $all ? 1 : 0);
                    $admin->log_slot_lock($sid, $slot_num, $all ? 1 : 0);
                    break;
                case 'slotUnlock':
                    swAPI::slotUnlock($sid, $slot_num, $all ? 1 : 0);
                    $admin->log_slot_unlock($sid, $slot_num, $all ? 1 : 0);
                    break;
                case 'query':
                    swAPI::query_info($sid, $slot_num, $all ? 1 : 0);
                    $admin->log_query_info($sid, $slot_num, $all ? 1 : 0);
                    break;
                case 'sync_umbrella':
                    swAPI::sync_umbrella($sid);
                    $admin->log_sync_umbrella($sid);
                    break;
                case 'lend':
                    swAPI::manually_lend($sid, $slot_num, $all ? 1 : 0);
                    $admin->log_manually_lend($sid, $slot_num, $all ? 1 : 0);
                    break;
                case 'reboot':
                    swAPI::reboot($sid);
                    $admin->log_reboot($sid);
                    break;
                case 'module_num':
                    swAPI::module_num($sid, $module_num);
                    $admin->log_module_num($sid, $module_num);
                    break;
                case 'upgrade':
                    swAPI::upgrade($sid, $file_name, $file_size);
                    $admin->log_upgrade($sid, $file_name, $file_size);
                    break;
                case 'init_set':
                    swAPI::initSet($sid);
                    $admin->log_init_set($sid);
                    break;
                case 'element_module_open':
                    swAPI::elementModuleOpen($sid);
                    $admin->log_element_module_open($sid);
                    break;
                case 'element_module_close':
                    swAPI::elementModuleClose($sid);
                    $admin->log_element_module_close($sid);
                    break;
                case 'voice_module_open':
                    swAPI::voiceModuleOpen($sid);
                    $admin->log_voice_module_open($sid);
                    break;
                case 'voice_module_close':
                    swAPI::voiceModuleClose($sid);
                    $admin->log_voice_module_close($sid);
                    break;
                case 'show_qrcode':
                    if (!ct('station')->fetch($sid))
                    {
                        echo '站点不存在';
                        exit;
                    }
                    require_once JJSAN_DIR_PATH . 'lib/alipay/AlipayAPI.php';
                    $wechatQrcodeUrl = getLimitQrcodeUrl($sid, 60);
                    $alipayQrcodeUrl = AlipayAPI::createQrcode($sid, 60);
                    include template("jjsan:/cp/station/show_qrcode");
                    exit;
                default:
            }
            Api::output();
            exit;
        }
        $accessStation = null;
        // 非全局搜索权限
        if (!$auth->globalSearch)
        {
            $accessShops   = $auth->getAllAccessShops();
            $accessStation = $shop->getStationIdsByShopIds($accessShops);
            $accessStation = array_filter($accessStation);
        }
        //站点导出功能
        if ($_GET['export'] && $cdo['export'])
        {
            $stations         = $station->searchStation($_GET, '', '', $accessStation);
            $stations['data'] = array_map(function ($a) use ($station) {
                $a['sync_time']      = date('Y-m-d H:i:s', $a['sync_time']);
                $a['network_status'] = $station->isStationOnline($a['id']) ? '是' : '否';
                $a['maintain_name']  = '';
                $a['maintain_role']  = '';
                $a['phone']          = '';
                // @todo optimize
                $shopStation = ct('shop_station')->where(['station_id' => $a['id']])->first();
                if ($shopStation['shopid'])
                {
                    $adminShop = ct('admin_shop')
                        ->where(['shop_id' => $shopStation['shopid'], 'status'  => table_jjsan_admin_shop::STATUS_PASS])
                        ->first();
                    if ($adminShop['admin_id'])
                    {
                        $admin              = ct('admin')->fetch($adminShop['admin_id']);
                        $a['maintain_name'] = $admin['name'];
                        $a['maintain_role'] = ct('admin_role')->fetch($admin['role_id'])['role'];
                    }
                    $a['phone'] = ct('shop')->fetch($shopStation['shopid'])['phone'];
                }

                return $a;
            }, $stations['data']);
            $sheetarray[]     = create_excel_column($stations['data'], 'id', '站点ID');
            $sheetarray[]     = create_excel_column($stations['data'], 'title', '站点名称');
            $sheetarray[]     = create_excel_column($stations['data'], 'address', '具体地址');
            $sheetarray[]     = create_excel_column($stations['data'], 'phone', '联系电话');
            $sheetarray[]     = create_excel_column($stations['data'], 'network_status', '是否在线');
            $sheetarray[]     = create_excel_column($stations['data'], 'total', '雨伞总数');
            $sheetarray[]     = create_excel_column($stations['data'], 'usable', '可借数');
            $sheetarray[]     = create_excel_column($stations['data'], 'empty', '可还数');
            $sheetarray[]     = create_excel_column($stations['data'], 'voltage', '设备电压');
            $sheetarray[]     = create_excel_column($stations['data'], 'sync_time', '最后同步时间');
            $sheetarray[]     = create_excel_column($stations['data'], 'maintain_role', '维护者角色');
            $sheetarray[]     = create_excel_column($stations['data'], 'maintain_name', '维护人');
            $sheetarray       = transpose($sheetarray);
            export_excel($sheetarray, 'StationList_' . date("Ymd"));
            exit;
        }
        $stations         = $station->searchStation($_GET, $page, $pageSize, $accessStation);
        $stations['data'] = array_map(function ($a) use ($station, $umbrella_outside_sync, $shop) {
            // 雨伞同步判断 开启同步筛选的话所有值均为true
            $a['has_outside_sync_umbrella'] = $umbrella_outside_sync ==
                                              'on' ? true : $station->isStationHasumbrellaSync($a['id']);
            // 设备是否在线
            $a['network_status'] = $station->isStationOnline($a['id']);
            // 所属商铺名称
            $a['shopname'] = $shop->getShopInfoByStationId($a['id'])['name'];
            // 同步策略名称
            $a['station_settings_name'] = $shop->getStationSettingsNameByStationId($a['id']) ?: '全局配置';

            return $a;
        }, $stations['data']);
        // 所有省份
        $provinces = array_map(function ($v) {
            return $v['province'];
        }, $area_nav_tree);
        unset($_GET['page']);
        $pagehtm = getPages($stations['count'], $page - 1, RECORD_LIMIT_PER_PAGE, '/index.php?' . http_build_query($_GET));
        break;
    // 站点设置列表
    case 'setting-list':
        if (isset($do))
        {
            switch ($do)
            {
                // 升级设置策略
                case 'setting_strategy':
                    if (!$auth->globalSearch && !$auth->checkStationIdIsAuthorized($sid))
                    {
                        echo 'unauthorized station';
                        exit;
                    }
                    if ($_POST)
                    {
                        $res = $admin->sync_strategy($station, $sid, $strategy_id);
                        if ($res)
                        {
                            Api::output([], 0, '更新成功');
                        } else
                        {
                            Api::output([], 1, '更新失败');
                        }
                        exit;
                    }
                    $jjsan_station_settings = ct('station_settings');
                    $stationSettingId       = $station->getStationSetting($sid);
                    $settings               = $jjsan_station_settings->all_settings();
                    include template('jjsan:cp/station/setting_strategy');
                    break;
                default:
            }
            exit;
        }
        break;
    // 地区设置
    case 'region_settings':
        $title_list = getStationCity();
        $stations   = ct('shop_station')->getErrorMans();
        $error_mans = [];
        foreach ($stations as $key => $value)
        {
            $error_man            = json_decode($value, true);
            $error_man['region']  = $error_man['region'] ?: [];
            $error_man['station'] = $error_man['station'] ?: [];
            $error_mans           = array_merge($error_mans, $error_man['region'], $error_man['station']);
        }
        break;
    // 批量导入
    case 'batch_import':
        if ($path)
        {
            $file = fopen($path, 'r');
            $data = [];
            while ($res = fgets($file))
            {
                $data[] = explode(',', str_replace("\n", '', $res));
            }
            $columns = ['id', 'title', 'mac'];
            for ($i = 0; $i < count($data); $i++)
            {
                foreach ($columns as $k => $v)
                {
                    $arr[$columns[$k]] = $data[$i][$k];
                }
                ct('station')->insert($arr, true, true); //重复就替换掉
            }
        }
        break;
    // 雨伞列表
    case 'umbrella_detail':
        $umbrellaCounts = ct('station')->fetch($sid)['usable'];
        // 返回的数据按照slot顺序 sync_time倒叙排列
        $umbrellas = ct('umbrella')->getLimitedUmbrellas($sid, $umbrellaCounts);
        // 排序
        $umbrellas = multi_array_sort($umbrellas, 'slot');
        foreach ($umbrellas as &$u)
        {
            $u['sync_time']      = empty($u['sync_time']) ? '无' : date('Y-m-d H:i:s', $u['sync_time']);
            $u['exception_time'] = empty($u['exception_time']) ? '无' : date('Y-m-d H:i:s', $u['exception_time']);
            $u['heart_time']     = empty($u['heart_time']) ? '无' : date('Y-m-d H:i:s', $u['heart_time']);
        }
        include template('jjsan:cp/station/umbrella_detail');
        exit;
        break;
    // 站点详情
    case 'station_detail':
        if (!$auth->globalSearch && !$auth->checkStationIdIsAuthorized($sid))
        {
            echo 'unauthorized station';
            exit;
        }
        $station      = ct('station')->fetch($sid);
        $shop_station = ct('shop_station')->fetch_by_field('station_id', $sid);
        $sync_time    = date("Y-m-d H:i:s", $station['sync_time']);
        include template('jjsan:cp/station/detail');
        exit;
    case 'update_station_detail':
        if (!$auth->globalSearch && !$auth->checkStationIdIsAuthorized($sid))
        {
            echo 'unauthorized station';
            exit;
        }
        // station
        $station_update = ['title' => $_GET['title'], 'address' => $_GET['address'],];
        ct('station')->update($sid, $station_update);
        // shop_station
        $shop_station        = ct('shop_station')->fetch_by_field('station_id', $sid);
        $shop_station_update = array_merge(['desc' => $_GET['desc'],], $station_update);
        ct('shop_station')->update($shop_station['id'], $shop_station_update);
        updateStationToLBS($shop_station['lbsid'], $shop_station_update);
        redirect(lang('plugin/jjsan', 'settings_update_success'), "index.php?mod=cp&act=station&opt=list");
        exit;
    case 'heartbeat_log':
        if ($sid)
        {
            $beginTime                   = $sdate ? strtotime($sdate) : strtotime(date("Y-m-d"));
            $endTime                     = $edate ? strtotime($edate) : $beginTime + 86400;
            $jjsan_station_heartbeat_log = ct('station_heartbeat_log');
            $logs                        = $jjsan_station_heartbeat_log->fetch_all_by_search($shop_station_id, $beginTime, $endTime, $sid);
            $times                       = array_column($logs, 'created_at');
            $offline_info                = [];
            $offline_time                = 0;
            $offline_count               = 0;
            for ($i = 0; $i < count($times) - 1; $i++)
            {
                $delta = $times[$i] - $times[$i + 1];
                // 超过3个心跳记录认为机器掉线了
                if ($delta > STATION_HEARTBEAT * 3)
                {
                    $offline_info[$times[$i + 1]] = humanTime($delta);
                    $offline_time                 += $delta;
                    $offline_count                += 1;
                }
            }
            if (!$offline_time)
            {
                $alive_time = humanTime($endTime - $beginTime);
            } else
            {
                $alive_time = humanTime($endTime - $beginTime - $offline_time);
            }
            if ($export)
            {
                $name = 'log_' . time();
                header("Content-type:application/vnd.ms-excel");
                header("Content-Disposition:filename=$name.xls");
                echo "时间段：\t";
                echo "$sdate - $edate\t\n";
                echo "在线总时长：\t";
                echo "$alive_time\t\n";
                echo "离线次数：\t";
                echo "$offline_count\t\n";
                echo "\n";
                echo "离线时间\t";
                echo "离线时长\t\n";
                foreach ($offline_info as $k => $v)
                {
                    $k = date("Y-m-d H:i:s", $k);
                    echo "$k\t";
                    echo "$v\t\n";
                }
                exit;
            }
            $start         = ($page - 1) * RECORD_LIMIT_PER_PAGE;
            $num           = $jjsan_station_heartbeat_log->count_all_by_search($shop_station_id, $beginTime, $endTime, $sid);
            $heartbeat_log = $jjsan_station_heartbeat_log->fetch_all_by_search($shop_station_id, $beginTime, $endTime, $sid, $start, RECORD_LIMIT_PER_PAGE);
            unset($_GET['page']);
            $params  = implode_with_key($_GET);
            $url     = "{$_SERVER['PHP_SELF']}?$params";
            $pagehtm = getPages($num, $page - 1, RECORD_LIMIT_PER_PAGE, $url);
        }
        break;
    case 'umbrella_export':
        $curStation     = C::t('common_setting')->fetch('jjsan_umbreall_export_default_station') ?: 1078;
        $umbrellaCounts = ct('station')->fetch($curStation)['usable'];
        // 返回的数据按照slot顺序 sync_time倒叙排列
        $umbrellas = ct('umbrella')->getLimitedUmbrellas($curStation, $umbrellaCounts);
        // 排序
        $umbrellas = multi_array_sort($umbrellas, 'slot');
        foreach ($umbrellas as &$u)
        {
            $u['sync_time']      = empty($u['sync_time']) ? '无' : date('Y-m-d H:i:s', $u['sync_time']);
            $u['exception_time'] = empty($u['exception_time']) ? '无' : date('Y-m-d H:i:s', $u['exception_time']);
            $u['heart_time']     = empty($u['heart_time']) ? '无' : date('Y-m-d H:i:s', $u['heart_time']);
        }
        if (isset($ajax))
        {
            if (!ENV_DEV)
                return '';
            if (empty($umbrellas))
                return '';
            $umbrellas = array_map(function ($a) {
                $b[0] = $a['id'];
                $b[1] = $a['slot'];
                $b[2] = $a['sync_time'];

                return $b;
            }, $umbrellas);
            $excelObj  = new PHPExcel();
            $excelObj->setActiveSheetIndex(0);
            $excelObj->getActiveSheet()
                     ->setCellValue('A1', 'ID')
                     ->setCellValue('B1', 'SLOT')
                     ->setCellValue('C1', 'SYNC_TIME');
            $excelObj->getActiveSheet()->fromArray($umbrellas, null, 'A2');
            $fileName = $curStation . '_' . date('Y-m-d_H_i_s') . '.xls';
            // Redirect output to a client’s web browser (Excel5)
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename=' . $fileName);
            header('Cache-Control: max-age=0');
            // If you're serving to IE 9, then the following may be needed
            header('Cache-Control: max-age=1');
            // If you're serving to IE over SSL, then the following may be needed
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
            header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
            header('Pragma: public'); // HTTP/1.0
            DB::query('DELETE FROM %t WHERE %i', ['jjsan_umbrella', DB::field('station_id', $curStation)]);
            $objWriter = PHPExcel_IOFactory::createWriter($excelObj, 'Excel5');
            $objWriter->save('php://output');
            exit;
        }
        break;
    case 'umbrella_export_2':
        $curStation     = C::t('common_setting')->fetch('jjsan_umbreall_export_default_station_2') ?: 2004;
        $umbrellaCounts = ct('station')->fetch($curStation)['usable'];
        // 返回的数据按照slot顺序 sync_time倒叙排列
        $umbrellas = ct('umbrella')->getLimitedUmbrellas($curStation, $umbrellaCounts);
        // 排序
        $umbrellas = multi_array_sort($umbrellas, 'slot');
        foreach ($umbrellas as &$u)
        {
            $u['sync_time']      = empty($u['sync_time']) ? '无' : date('Y-m-d H:i:s', $u['sync_time']);
            $u['exception_time'] = empty($u['exception_time']) ? '无' : date('Y-m-d H:i:s', $u['exception_time']);
            $u['heart_time']     = empty($u['heart_time']) ? '无' : date('Y-m-d H:i:s', $u['heart_time']);
        }
        if (isset($ajax))
        {
            if (!ENV_DEV)
                return '';
            if (empty($umbrellas))
                return '';
            $umbrellas = array_map(function ($a) {
                $b[0] = $a['id'];
                $b[1] = $a['slot'];
                $b[2] = $a['sync_time'];

                return $b;
            }, $umbrellas);
            $excelObj  = new PHPExcel();
            $excelObj->setActiveSheetIndex(0);
            $excelObj->getActiveSheet()
                     ->setCellValue('A1', 'ID')
                     ->setCellValue('B1', 'SLOT')
                     ->setCellValue('C1', 'SYNC_TIME');
            $excelObj->getActiveSheet()->fromArray($umbrellas, null, 'A2');
            $fileName = $curStation . '_' . date('Y-m-d_H_i_s') . '.xls';
            // Redirect output to a client’s web browser (Excel5)
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename=' . $fileName);
            header('Cache-Control: max-age=0');
            // If you're serving to IE 9, then the following may be needed
            header('Cache-Control: max-age=1');
            // If you're serving to IE over SSL, then the following may be needed
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
            header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
            header('Pragma: public'); // HTTP/1.0
            DB::query('DELETE FROM %t WHERE %i', ['jjsan_umbrella', DB::field('station_id', $curStation)]);
            $objWriter = PHPExcel_IOFactory::createWriter($excelObj, 'Excel5');
            $objWriter->save('php://output');
            exit;
        }
        break;
    // 站点统计日志
    case 'station_log':
        if ($start_time)
        {
            $pre   = date('Ymd', strtotime($start_time));
            $where = null;
            if ($province || $city || $area)
            {
                if (!checkProvenceCityAreaLegal($area_nav_tree, $province, $city, $area))
                {
                    redirect('省市区不存在');
                }
                if ($province)
                    $search['province'] = $province;
                if ($city)
                    $search['city'] = $city;
                if ($area)
                    $search['area'] = $area;
                $searchShops        = ct('shop')->select('id')->where($search)->get();
                $searchShopIds      = array_column($searchShops, 'id');
                $searchShopStations = ct('shop_station')
                    ->select('station_id')
                    ->where(['shopid' => $searchShopIds])
                    ->get();
                $searchStationIds   = array_column($searchShopStations, 'station_id');
                $searchStationIds   = array_filter($searchStationIds);
                if ($station_id)
                {
                    $searchStationIds = in_array($station_id, $searchStationIds) ? $station_id : [];
                }
                $where['station_id'] = $searchStationIds;
            } else
            {
                if ($station_id)
                    $where['station_id'] = $station_id;
            }
            // 使用stationlog里面的station id
            $curStationLog          = ct('station_log')
                ->where($where)
                ->stringWhere(' and left(id, 8) = ' . $pre)
                ->limit(($page - 1) * $pageSize, $pageSize)
                ->get();
            $curStationLog          = array_map(function ($a) {
                $a['new_station_id'] = substr($a['id'], 8);

                return $a;
            }, $curStationLog);
            $curStationLogStationId = array_column($curStationLog, 'new_station_id');
            $shopStationInfo        = ct('shop_station')->where(['station_id' => $curStationLogStationId])->get();
            foreach ($shopStationInfo as $v)
            {
                $newShopStationInfo[$v['station_id']] = $v;
            }
            $curStationLog = array_map(function ($a) use ($newShopStationInfo) {
                // 负责人
                if (key_exists($a['new_station_id'], $newShopStationInfo))
                {
                    $a['shop_station_name'] = $newShopStationInfo[$a['new_station_id']]['title'];
                    // @todo optimize
                    if ($shopId = $newShopStationInfo[$a['new_station_id']]['shopid'])
                    {
                        $adminShop = ct('admin_shop')
                            ->where(['shop_id' => $shopId, 'status' => table_jjsan_admin_shop::STATUS_PASS])
                            ->first();
                        if ($adminShop['admin_id'])
                        {
                            $admin              = ct('admin')->fetch($adminShop['admin_id']);
                            $a['maintain_name'] = $admin['name'];
                            $a['maintain_role'] = ct('admin_role')->fetch($admin['role_id'])['role'];
                        }
                    }
                }
                $a['shop_station_name'] = $a['shop_station_name'] ?: '-';
                $a['maintain_role']     = $a['maintain_role'] ?: '-';
                $a['maintain_name']     = $a['maintain_name'] ?: '-';
                // 雨伞保有量等
                if ($a['rssi_info'])
                {
                    $a['rssi_info_desc'] = implode('/', json_decode($a['rssi_info'], true));
                } else
                {
                    $a['rssi_info_desc'] = '0/0/0/0/0';
                }
                if ($a['umbrella_from_station'])
                {
                    $a['umbrella_from_station_desc'] = implode('/', json_decode($a['umbrella_from_station'], true));
                } else
                {
                    $a['umbrella_from_station_desc'] = '0/0/0/0/0';
                }
                if ($a['slot_from_station'])
                {
                    $a['slot_from_station_desc'] = implode('/', json_decode($a['slot_from_station'], true));
                } else
                {
                    $a['slot_from_station_desc'] = '0/0/0/0/0';
                }

                return $a;
            }, $curStationLog);
            // 固定统计日期
            $hourArray        = [2, 7, 12, 17, 22];
            $allStationLog    = ct('station_log')->where($where)->stringWhere(' and left(id, 8) = ' . $pre)->get();
            $allStationLogCnt = count($allStationLog);
            // 雨伞保有量总数
            $allStationLogUmbrellaFromStation = array_column($allStationLog, 'umbrella_from_station');
            $allStationLogUmbrellaFromStation = array_filter($allStationLogUmbrellaFromStation, function ($a) {
                if ($a !== '')
                    return true;
            });
            $totalUmbrellaFromStation         = [];
            foreach ($allStationLogUmbrellaFromStation as $v)
            {
                $tmp = json_decode($v, true);
                foreach ($hourArray as $vv)
                {
                    $totalUmbrellaFromStation[$vv] += $tmp[$vv];
                }
            }
            $totalUmbrellaFromStation = implode('/', $totalUmbrellaFromStation);
            // 槽位总数
            $allStationLogSlotFromStation = array_column($allStationLog, 'slot_from_station');
            $allStationLogSlotFromStation = array_filter($allStationLogSlotFromStation, function ($a) {
                if ($a !== '')
                    return true;
            });
            $totalSlotFromStation         = [];
            foreach ($allStationLogSlotFromStation as $v)
            {
                $tmp = json_decode($v, true);
                foreach ($hourArray as $vv)
                {
                    $totalSlotFromStation[$vv] += $tmp[$vv];
                }
            }
            $totalSlotFromStation = implode('/', $totalSlotFromStation);
            unset($_GET['page']);
            $pagehtm = getPages($allStationLogCnt, $page - 1, RECORD_LIMIT_PER_PAGE, '/index.php?' . http_build_query($_GET));
            // 导出功能
            if ($_GET['export'])
            {
                // 需要重新整理数据
                $allStationLog          = ct('station_log')
                    ->where($where)
                    ->stringWhere(' and left(id, 8) = ' . $pre)
                    ->get();
                $allStationLog          = array_map(function ($a) {
                    $a['new_station_id'] = substr($a['id'], 8);

                    return $a;
                }, $allStationLog);
                $allStationLogStationId = array_column($allStationLog, 'new_station_id');
                $shopStationInfo        = ct('shop_station')->where(['station_id' => $allStationLogStationId])->get();
                unset($newShopStationInfo);
                foreach ($shopStationInfo as $v)
                {
                    $newShopStationInfo[$v['station_id']] = $v;
                }
                $allStationLog = array_map(function ($a) use ($newShopStationInfo) {
                    // 负责人
                    if (key_exists($a['new_station_id'], $newShopStationInfo))
                    {
                        $a['shop_station_name'] = $newShopStationInfo[$a['new_station_id']]['title'];
                        // @todo optimize
                        if ($shopId = $newShopStationInfo[$a['new_station_id']]['shopid'])
                        {
                            $adminShop = ct('admin_shop')
                                ->where(['shop_id' => $shopId, 'status' => table_jjsan_admin_shop::STATUS_PASS])
                                ->first();
                            if ($adminShop['admin_id'])
                            {
                                $admin              = ct('admin')->fetch($adminShop['admin_id']);
                                $a['maintain_name'] = $admin['name'];
                                $a['maintain_role'] = ct('admin_role')->fetch($admin['role_id'])['role'];
                            }
                        }
                    }
                    $a['shop_station_name'] = $a['shop_station_name'] ?: '';
                    $a['maintain_role']     = $a['maintain_role'] ?: '';
                    $a['maintain_name']     = $a['maintain_name'] ?: '';
                    // 雨伞保有量等
                    if ($a['rssi_info'])
                    {
                        $a['rssi_info_desc'] = implode('/', json_decode($a['rssi_info'], true));
                    } else
                    {
                        $a['rssi_info_desc'] = '0/0/0/0/0';
                    }
                    if ($a['umbrella_from_station'])
                    {
                        $a['umbrella_from_station_desc'] = implode('/', json_decode($a['umbrella_from_station'], true));
                    } else
                    {
                        $a['umbrella_from_station_desc'] = '0/0/0/0/0';
                    }
                    if ($a['slot_from_station'])
                    {
                        $a['slot_from_station_desc'] = implode('/', json_decode($a['slot_from_station'], true));
                    } else
                    {
                        $a['slot_from_station_desc'] = '0/0/0/0/0';
                    }

                    return $a;
                }, $allStationLog);
                $sheetarray[]  = create_excel_column($allStationLog, 'new_station_id', '站点ID');
                $sheetarray[]  = create_excel_column($allStationLog, 'shop_station_name', '商铺站点名称');
                $sheetarray[]  = create_excel_column($allStationLog, 'maintain_role', '归属角色');
                $sheetarray[]  = create_excel_column($allStationLog, 'maintain_name', '负责人');
                $sheetarray[]  = create_excel_column($allStationLog, 'umbrella_from_station_desc', '雨伞保有量(2/7/12/17/22)');
                $sheetarray[]  = create_excel_column($allStationLog, 'slot_from_station_desc', '雨伞槽位投放量(2/7/12/17/22)');
                $sheetarray[]  = create_excel_column($allStationLog, 'rssi_info_desc', '信号分布(2/7/12/17/22)');
                $sheetarray[]  = create_excel_column($allStationLog, 'max_umbrella_count', '最大雨伞数');
                $sheetarray[]  = create_excel_column($allStationLog, 'min_umbrella_count', '最小雨伞数');
                $sheetarray[]  = create_excel_column($allStationLog, 'online_time', '开机时长(分钟)');
                $sheetarray[]  = create_excel_column($allStationLog, 'login_count', '机器登录次数');
                $sheetarray    = transpose($sheetarray);
                array_unshift($sheetarray, [$pre . '站点统计日志', '', '', '总计', $totalUmbrellaFromStation, $totalSlotFromStation]);
                export_excel($sheetarray, 'StationLogList_' . $pre);
                exit;
            }
        }
        break;
    default:
        redirect("您找的页面不存在");
}
