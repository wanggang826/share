<?php
use model\Api;

switch($opt){

    case "index":
        break;

    case "pwd":
        if($_POST) {
            if($admin->changePassword($oldpassword, $newpassword)) {
                redirect('密码更新成功', $_SERVER['HTTP_REFERER']);
            } else {
                redirect('密码更新失败', $_SERVER['HTTP_REFERER']);
            }
        }
        break;

    case "help":
        break;

    case 'login':
        if($_POST) {
            if($admin->login($username, $password)) {
                header('location:index.php?mod=cp&act=admin&opt=help');
                exit;
            }
            redirect('用户名或密码不正确', $_SERVER['HTTP_REFERER']);
        }
        if($admin->isLogin()) {
            header('location:index.php?mod=cp&act=admin&opt=help');
            exit;
        }
        break;

    case "register":
        if($_POST) {
            if ($company == '1') $company = $newcompany;
            if($admin->register($username, $password, $email, $name, $company, $auth_id)) {
                redirect('注册成功,请耐心等待审核', 'index.php?mod=cp&act=admin&opt=help');
            } else {
                redirect('注册失败', $_SERVER['HTTP_REFERER']);
            }
        }
        $roles = $auth->allCanRegisterRoles();
        break;

    case 'logout':
        $admin->logout();
        header('location:index.php?mod=cp&act=admin&&opt=login');
        exit;

    case "role":
        if(isset($do)){
            // 添加角色 操作
            if($do == 'add'){
                if($ajax){
                    if ($auth->isAuthorizedAction($access, $jjsan_nav_tree) && $auth->createNewRole($role, $access, $global_search)) {
                        $admin->log_add_role($role);
                        Api::output([], 0, '角色创建成功');
                    } else {
                        Api::output([], 1, '角色创建失败');
                    }
                    exit;
                }else{
                    include template("jjsan:cp/admin/role_add");
                    exit;
                }
            }

            // edit
            if($do == 'edit'){
                if ($ajax) {
                    if ($auth->isAuthorizedAction($access, $jjsan_nav_tree) && $auth->updateRoleAccess($role_id, $role, $access, $global_search)) {
                        $admin->log_edit_role($role_id, $role);
                        Api::output([], 0, '角色更新成功');
                    } else {
                        Api::output([], 1, '角色更新失败');
                    }
                    exit;
                } else {
                    $role = $auth->getRoleInfo($role_id);
                    $role['access'] = json_decode($role['access']);
                    include template("jjsan:cp/admin/role_edit");
                    exit;
                }
            }
        }

        // 显示角色页面
        $roles = $auth->getAllRoles();
        break;

    case 'user_manage':
        if(isset($do)) {
            switch ($do) {
                case 'pass':
                case 'refuse':
                case 'lock':
                case 'unlock':
                case 'delete':
                case 'resume':
                    // 非自己
                    if ($uid != $admin->adminInfo['id']) {
                        // 不支持同级别角色变动彼此账户状态
                        if ($admin->isTheSameRole([$uid, $admin->adminInfo['id']])) {
                            Api::output([], 1, '操作失败');
                            exit;
                        }
                    }
                    if($admin->handleRoleApplyUsers($uid, $do)) {
                        Api::output([], 0, '操作成功');
                    } else {
                        Api::output([], 1, '操作失败');
                    }
                    exit;
                    break;

                case 'search':
                    exit;
                    break;

                case 'edit':
                    if ($admin_id) {
                        if ($_POST) {
                            $rst = $admin->updateAdminUserInfo($_POST);
                            if ($rst) {
                                Api::output([], 0, '更新成功');
                            } else {
                                Api::output([], 1, '更新失败');
                            }
                            exit;
                        }
                        $adminInfo = $admin->getAdminUserInfo($admin_id);
                        // 非超级管理员不显示超级管理员信息
                        if ($admin->adminInfo['role_id'] != SUPER_ADMINISTRATOR_ROLE_ID && $adminInfo['role_id'] == SUPER_ADMINISTRATOR_ROLE_ID) {
                            $adminInfo = [];
                        }
                        include template("jjsan:cp/admin/user_edit");
                        exit;
                    }
                    break;
            }
        }
        $lists = $admin->allUsers($page, RECORD_LIMIT_PER_PAGE);
        // 非超级管理员不显示超级管理员信息
        if ($admin->adminInfo['role_id'] != SUPER_ADMINISTRATOR_ROLE_ID) {
            foreach ($lists as $v) {
                if ($v['role_id'] != SUPER_ADMINISTRATOR_ROLE_ID) {
                    $userLists[] = $v;
                }
            }
        } else {
            $userLists = $lists;
        }
        $pagehtm = getPages(
            $admin->allUsersCount(),
            $page - 1,
            RECORD_LIMIT_PER_PAGE,
            'index.php?mod=cp&act=admin&opt=user_manage'
        );
        $roles = $auth->allRoles();
        foreach($roles as $v) {
            $rolesArray[$v['id']] = $v['role'];
        }
        break;

    case 'access_apply':
        if(isset($do)) {
            switch ($do) {
                case 'city_apply':
                    // 转一维数组为二维数组 省份为key， 城市为value(数组）
                    foreach ($cities as $v) {
                        $tmp = explode('/', $v);
                        if (!isset($tmp[1])) {
                            $provinces[]= $v;
                        }
                    }
                    foreach($provinces as $v) {
                        foreach($cities as $v1) {
                            $tmp = explode('/', $v1);
                            if ($tmp[0] == $v && isset($tmp[1])) {
                                $newCities[$v][] = $tmp[1];
                            }
                        }
                    }
                    if ($auth->isAuthorizedCity($cities, $area_nav_tree) && $auth->addCityAccess($newCities)) {
                        Api::output([], 0, '申请城市权限成功');
                    } else {
                        Api::output([], 1, '申请城市权限失败');
                    }
                    break;

                case 'city_modify':
                    // 转一维数组为二维数组 省份为key， 城市为value(数组）
                    foreach ($cities as $v) {
                        $tmp = explode('/', $v);
                        if (!isset($tmp[1])) {
                            $provinces[]= $v;
                        }
                    }
                    foreach($provinces as $v) {
                        foreach($cities as $v1) {
                            $tmp = explode('/', $v1);
                            if ($tmp[0] == $v && isset($tmp[1])) {
                                $newCities[$v][] = $tmp[1];
                            }
                        }
                    }
                    if ($auth->isAuthorizedCity($cities, $area_nav_tree) && $auth->modifyCityAccess($newCities)) {
                        Api::output([], 0, '申请城市权限成功');
                    } else {
                        Api::output([], 1, '申请城市权限失败');
                    }
                    break;

                case 'city_delete':
                    if ($auth->deleteCurrentCitesAccess()) {
                        Api::output([], 0, '城市权限删除成功');
                    } else {
                        Api::output([], 1, '城市权限删除失败');
                    }
                    break;

                case "shop_apply":
                    if ($auth->addShopAccess($shop_id)) {
                        Api::output([], 0, '商铺权限申请成功');
                    }else{
                        Api::output([], 1, '商铺权限申请失败');
                    }
                    break;

                case "shop_delete":
                    if ($auth->deleteShopAccess($admin_shop_id)) {
                        Api::output([], 0, '商铺权限删除成功');
                    }else{
                        Api::output([], 1, '商铺权限删除失败');
                    }
                    break;

                default:
                    break;
            }
            exit;
        }
        $cityStatus =  $auth->checkCityStatus();
        $cities = $auth->getCity();

        // 已经申请的商铺
        $shop_applys_key = [];
        $shop_applys = $auth->getCurrentApplyCites();
        foreach ($shop_applys as &$shop_apply) {
            $shop_applys_key[] = $shop_apply['shop_id'];
            $c = ct('shop') -> fetch_all($shop_apply['shop_id']);
            $shop_apply['shop_name'] = $c[$shop_apply['shop_id']]['name'];
            $shop_apply['status'] = table_jjsan_admin_shop::$STATUS[$shop_apply['status']];
            $shop_apply['shop_locate'] =
                $c[$shop_apply['shop_id']]['province'] .
                $c[$shop_apply['shop_id']]['city'] .
                $c[$shop_apply['shop_id']]['area'] .
                $c[$shop_apply['shop_id']]['locate'];
        }

        // 查询商铺
        if($shop_search || $province || $city || $area){
            if (!empty($shop_search)) $where['name'] = ['value' => '%'.$shop_search.'%', 'glue' => 'like'];
            if (!empty($province)) $where['province'] = $province;
            if (!empty($city)) $where['city'] = $city;
            if (!empty($area)) $where['area'] = $area;
            // 去除申请中或者已经申请的商铺
            $appliedShops = ct('admin_shop')->select('shop_id')->get();
            $appliedShopIds = array_column($appliedShops, 'shop_id');
            if ($appliedShopIds) {
                $where['id'] = ['value' => $appliedShopIds, 'glue' => 'notin'];
            }
            $shops = ct('shop')->select('id,name,locate')->where($where)->get();
        }

        // 所有省份
        $provinces = array_map(function($v){
            return $v['province'];
        }, $area_nav_tree);

		break;

    case 'access_verify':
        if(isset($do)) {
            switch ($do) {
                case 'pass':
                    if($auth->handleCityApplyUsers($uid, $do)) {
                        Api::output([], 0, '操作成功');
                    } else {
                        Api::output([], 1, '操作失败');
                    }
                    exit;
                    break;
                default:
            }
            exit;
        }
        $pagehtm = getPages(
            $auth->allCitesAccessCount(),
            $page - 1,
            $pageSize,
            'index.php?mod=cp&act=admin&opt=access_verify'
        );
        $info = $auth->applyCitesInfo($page, $pageSize);
        $info = array_map(function($v){
            $v['city'] = json_decode($v['city'], 1);
            return $v;
        }, $info);
        //p($info);
        break;

    case "shop_access_verify":
        if(isset($do)){
            switch ($do) {
                case 'pass':
                    if($auth->handleShopApplyUsers($admin_shop_id, $do)){
                        Api::output([], 0, '操作成功');
                    }else{
                        Api::output([], 1, '操作失败');
                    }
                    break;

                default:
            }
            exit;
        }
        // 分页
        $pagehtm = getPages(
            $auth->allShopsAccessCount(),
            $page - 1,
            $pageSize,
            'index.php?mod=cp&act=admin&opt=shop_access_verify'
        );
        $admin_shops = $auth->applyShopsInfo($page, $pageSize);
        foreach ($admin_shops as $k => &$v) {
            $admin_res = ct('admin') -> fetch($v['admin_id']);
            $v['status_text'] = table_jjsan_admin_shop::$STATUS[$v['status']];
            $v['name'] = $admin_res['name'];
            $v['company'] = $admin_res['company'];
            $v['role'] = ct('admin_role') -> fetch($admin_res['role_id'])['role'];
            $shopInfo = ct('shop') -> fetch($v['shop_id']);
            $v['shop_name'] = $shopInfo['name'];
            if ($shopInfo['province'] == $shopInfo['city']) {
                $shopInfo['city'] = '';
            }
            $v['shop_address'] = $shopInfo['province'].$shopInfo['city'].$shopInfo['area'].$shopInfo['locate'];
        }
        unset($v);
        break;

    case 'install_man_manage':
        $common_setting = C::t('common_setting');

        $items    = json_decode( $common_setting -> fetch('jjsan_install_man_verifying'), true ); // 待审核人员
        $installs = json_decode( $common_setting -> fetch('jjsan_install_man'), true );	 // 安装维护人员
        $users    = json_decode( $common_setting -> fetch('jjsan_install_man_user'), true );		// 普通用户

        // 添加上用户信息
        $data = [];
        foreach ($items as $key => $value) {
            $role_id = ct('user') -> fetch_by_field('id',$key)['role_id'];
            if($role_id ==0){
                $role = '普通用户';
            }else{
                foreach ($roles as $r) {
                    if($r['id'] == $role_id){
                        $role = $r['role'];
                    }
                }
            }
            $data[] = ['id'=>$key,'status'=>'待通过','name'=>$value,'role_id'=>$role_id,'role'=>$role];
        }

        foreach ($installs as $key => $value) {
            $role_id = ct('user') -> fetch_by_field('id',$key)['role_id'];
            if($role_id ==0){
                $role = '普通用户';
            }else{
                foreach ($roles as $r) {
                    if($r['id'] == $role_id){
                        $role = $r['role'];
                    }
                }
            }
            $data[] = ['id'=>$key,'status'=>'维护','name'=>$value,'role_id'=>$role_id,'role'=>$role];
        }

        foreach ($users as $key => $value) {
            $role_id = ct('user') -> fetch_by_field('id',$key)['role_id'];
            if($role_id ==0){
                $role = '普通用户';
            }else{
                foreach ($roles as $r) {
                    if($r['id'] == $role_id){
                        $role = $r['role'];
                    }
                }
            }
            $data[] = ['id'=>$key,'status'=>'普通用户','name'=>$value,'role_id'=>$role_id,'role'=>$role];
        }
        if (isset($do)) {
            switch ($do) {
                case 'add':
                    $qrcodeUrl = getLimitQrcodeUrl(INSTALL_MAN_ADD_SCENE_ID, QRCODE_LIMIT_TIME);
                    include template('jjsan:cp/admin/install_man_add');
                    exit;
                    break;

                case 'pass':
                    $installs["$id"] = $items["$id"];
                    unset($items["$id"]);
                    //　通过审核则通知用户
                    if(C::t('common_setting')->update('jjsan_install_man_verifying', json_encode($installs_verifying)) && C::t('common_setting')->update('jjsan_install_man', json_encode($installs))) {
                        $msg = [
                            'openid' => ct('user')->getField($id, 'openid'),
                            'first'  => lang('plugin/jjsan','passed_first_message'),
                            'name' => $installs["$id"],
                            'time' => time(),
                            'remark' => lang('plugin/jjsan','passed_remark_message')
                        ];
                        sendBindMsgToUser_unsafe($msg);
                    }
                    $admin->log_pass_install_man($id);
                    break;

                case 'set_common':
                    $users["$id"] = $installs["$id"];
                    unset($installs["$id"]);
                    C::t('common_setting')->update('jjsan_install_man_user', json_encode($users));
                    C::t('common_setting')->update('jjsan_install_man', json_encode($installs));
                    $admin->log_set_common($id);
                    break;

                case 'set_install':
                    $installs["$id"] = $users["$id"];
                    unset($users["$id"]);
                    C::t('common_setting')->update('jjsan_install_man_user', json_encode($users));
                    C::t('common_setting')->update('jjsan_install_man', json_encode($installs));
                    $admin->log_set_install($id);
                    break;

                case 'delete':
                    unset($items["$id"]);
                    unset($installs["$id"]);
                    unset($users["$id"]);
                    $common_setting -> update('jjsan_install_man', json_encode($installs));
                    $common_setting -> update('jjsan_install_man_user', json_encode($users));
                    $common_setting -> update('jjsan_install_man_verifying',json_encode($items));
                    $admin->log_delete_install($id);
                    break;
            }
        }
        break;
}
