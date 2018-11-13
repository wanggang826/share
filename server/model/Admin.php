<?php
namespace model;

use \C;

/**
 * 登录之前不提供phpsessid
 *
 */


class Admin extends Model {

    const PHP_SESSION_ID = 'phpsessid';
    const STATUS_DELETE                         = -1;   //当前状态为被删除
    const EVENT_REFUSE                          = 1;    // 拒绝创建账户申请
    const EVENT_PASS                            = 2;    // 通过创建账户申请
    const EVENT_DELETE                          = 3;    // 删除系统用户
    const EVENT_LOCK                            = 4;    // 锁定系统用户
    const EVENT_UNLOCK                          = 5;    // 解锁系统用户
    const EVENT_RESUME		                    = 6;	// 恢复系统用户
    const EVENT_EDIT	        	            = 7;	// 编辑系统用户信息
    const EVENT_SYSTEM_SETTINGS		            = 8;	// 设置全局同步策略
    const EVENT_STATION_SETTINGS_ADD            = 9;	// 添加局部同步策略
    const EVENT_STATION_SETTINGS_EDIT	        = 10;	// 编辑局部同步策略
    const EVENT_STATION_SETTINGS_DELETE	        = 11;	// 删除局部同步策略
    const EVENT_FEE_SETTINGS		            = 12;	// 设置局部收费策略
    const EVENT_GLOBAL_SETTINGS		            = 13;	// 设置客服电话
    const EVENT_LOCAL_FEE_SETTINGS_ADD	        = 14;	// 添加局部收费策略
    const EVENT_LOCAL_FEE_SETTINGS_EDIT	        = 15;	// 编辑局部收费策略
    const EVENT_LOCAL_FEE_SETTINGS_DELETE	    = 16;	// 删除局部收费策略
    const EVENT_SHOP_TYPE_ADD           	    = 17;	// 添加商铺类型
    const EVENT_SHOP_ADD                	    = 18;	// 添加商铺
    const EVENT_SHOP_EDIT                	    = 19;	// 编辑商铺
    const EVENT_LOGO_UPDATE                	    = 20;	// 更新logo
    const EVENT_CAROUSEL_UPDATE                 = 21;	// 更新轮播图
    const EVENT_CP_BIND                         = 22;	// PC端绑定商铺
    const EVENT_CP_UNBIND                       = 23;	// PC端解绑商铺
    const EVENT_CP_GO_UP                        = 24;	// PC上机
    const EVENT_CP_REPLACE                      = 25;	// PC换机
    const EVENT_CP_REMOVE                       = 26;	// PC撤机
    const EVENT_CP_SHOP_STATION_SETTINGS        = 27;	// PC更改商铺站点设置
    const EVENT_CP_SLOT_LOCK                    = 28;	// PC锁住槽位
    const EVENT_CP_SLOT_UNLOCK                  = 29;	// PC解锁槽位
    const EVENT_CP_QUERY                        = 30;	// PC槽位查询
    const EVENT_CP_LEND                         = 31;	// PC人工借出
    const EVENT_CP_SYNC                         = 32;	// PC同步雨伞
    const EVENT_CP_REBOOT                       = 33;	// PC人工重启设备
    const EVENT_CP_MODULE_NUM                   = 34;	// PC人工设置模组数
    const EVENT_CP_UPGRADE                      = 35;	// PC人工升级控制
    const EVENT_CP_SYNC_STRATEGY                = 36;	// PC设置站点同步策略
    const EVENT_CANCEL_ORDER                    = 37;	// PC手动撤销订单
    const EVENT_RETURN_BACK                     = 38;	// PC手动退款
    const EVENT_ADD_ROLE                        = 39;	// 添加角色
    const EVENT_EDIT_ROLE                       = 54;	// 编辑角色
    const EVENT_PASS_INSTALL_MAN                = 40;	// 通过维护人员申请
    const EVENT_SET_COMMON                      = 41;	// 设置为普通人员
    const EVENT_SET_INSTALL                     = 42;	// 设置为维护人员
    const EVENT_DELETE_INSTALL                  = 43;	// 删除维护人员
    const EVENT_INIT_SET                        = 44;	// 机器初始化
    const EVENT_PICTEXT_SETTINGS_ADD	        = 45;	// 添加图文消息配置
    const EVENT_PICTEXT_SETTINGS_EDIT	        = 46;	// 编辑图文消息配置
    const EVENT_PICTEXT_SETTINGS_DELETE	        = 47;	// 删除图文消息配置
    const EVENT_ELEMENT_MODULE_OPEN             = 48;	// 开启机器模组功能
    const EVENT_ELEMENT_MODULE_CLOSE            = 49;	// 关闭机器模组功能
    const EVENT_VOICE_MODULE_OPEN               = 50;	// 开启机器模组功能

    const EVENT_VOICE_MODULE_CLOSE              = 51;	// 关闭机器模组功能

    const EVENT_ADD_ZERO_FEE_USER               = 52;	// 增加零费用用户openid
    const EVENT_DELETE_ZERO_FEE_USER            = 53;	// 移除零费用用户openid


    private $admin;
    private $admin_session;
    private $salt_rand = '0123456789qwertyuiopasdfghjklzxcvbnm';
    private $phpsessid = '';
    private $admin_log;

    public $adminInfo = [];
    public $isSuperAdmin = false;

    public function __construct()
    {
        $this->admin                = ct('admin');
        $this->admin_session        = ct('admin_session');
        $this->admin_log            = ct('admin_log');
        $this->phpsessid            = getcookie(self::PHP_SESSION_ID); //未登录的用户phpsessid为空
    }

    public function createSessId()
    {
        $this->phpsessid = md5(time().$this->getSalt());
    }

    // todo: 验证码验证
    public function checkCaptcha()
    {
        return true;
    }

    public function login($username, $password)
    {
        if(!$this->checkCaptcha()) {
            return false;
        }
        $userInfo = $this->admin->where(['username' => $username, 'status' => ADMIN_USER_STATUS_NORMAL])->first();
        if($userInfo) {
            if($this->encrypt($password, $userInfo['salt']) == $userInfo['pwd']) {
                //检查同一个用户登录个数, 超过限制, 踢掉最早登录的用户

                //创建phpsessid, 插入admin_session表
                $this->createSessId();
                $this->admin_session->insert([
                    'admin_id' => $userInfo['id'],
                    'session' => $this->phpsessid,
                    'create_time' => date('Y-m-d H:i:s'),
                ]);

                //使用dz函数发送cookie
                dsetcookie(self::PHP_SESSION_ID, $this->phpsessid, ADMIN_SESSION_EXPIRED_TIME, '/', '/', false, true);

                //更新登录错误次数
                $this->admin->update($userInfo['id'], ['login_error' => 0]);

                return true;
            } else {
                //登录错误次数大于阀值时,锁定帐号,更新登录错误次数
                if($userInfo['login_error'] >= ADMIN_LOGIN_ERROR_NUMBER - 1) {
                    $this->admin->update($userInfo['id'], ['login_error' => $userInfo['login_error'] + 1, 'status' => ADMIN_USER_STATUS_LOCKED]);
                } else {
                    //更新登录错误次数
                    $this->admin->update($userInfo['id'], ['login_error' => $userInfo['login_error'] + 1]);
                }
            }
        }
        return false;
    }

    public function isLogin()
    {
        $admin_session = $this->admin_session->where(['session' => $this->phpsessid])->first();
        if(!$admin_session) return false;
        if(strtotime($admin_session['update_time']) + ADMIN_SESSION_EXPIRED_TIME <= time()) return false;
        // 验证通过后保存用户信息
        $this->adminInfo = $this->admin->fetch($admin_session['admin_id']);
        // 判断是否超级管理员
        if($admin_session['admin_id'] == SUPER_ADMINISTRATOR_ROLE_ID) $this->isSuperAdmin = true;
        // 更新session
        $this->updateSession();
        // 更新cookie过期时间
        $this->updateCookie();
        return true;
    }

    public function logout()
    {
        dsetcookie(self::PHP_SESSION_ID, $this->phpsessid, -1, '/', '/', false, true);
    }

    public function updateSession()
    {
        $this->admin_session->updateSession($this->phpsessid);
    }

    public function updateCookie()
    {
        dsetcookie(self::PHP_SESSION_ID, $this->phpsessid, ADMIN_SESSION_EXPIRED_TIME, '/', '/', false, true);
    }

    public function encrypt($password, $salt)
    {
        return md5(md5($password).md5($salt));
    }

    public function getSalt()
    {
        $salt_rand = str_shuffle($this->salt_rand);
        return substr($salt_rand, 0, 8);
    }

    public function register($username, $password, $email, $name, $company, $auth_id)
    {
        if($this->checkRegisterUsername($username)
            && $this->checkRegisterPassword($password)
            && $this->checkRegisterEmail($email)
            && $this->checkRegisterCompany($company)
            && $this->checkRegisterRoleId($auth_id)
        ) {
            $salt = $this->getSalt();
            $this->admin->insert([
                'username'      => $username,
                'name'          => $name,
                'email'         => $email,
                'company'       => $company,
                'status'         => ADMIN_USER_STATUS_APPLIED,
                'salt'          => $salt,
                'pwd'           => $this->encrypt($password, $salt),
                'role_id'       => $auth_id,
                'login_error'   => 0,
                'create_time'   => date('Y-m-d H:i:s'),
            ]);
            return true;
        }
        return false;
    }

    public function checkRegisterUsername($username)
    {
        $forbidden_username = [
            'admin',
            'root',
            'administrator',
        ];
        if(in_array($username, $forbidden_username)) return false;
        if(!preg_match('/^[a-zA-Z_\d]{6,100}$/', $username)) return false;
        if($this->admin->where(['username' => $username])->first()) return false;
        return true;
    }

    public function checkRegisterEmail($email)
    {
        if(!preg_match('/^[a-zA-Z0-9_-]+@[a-zA-Z0-9_-]+(\.[a-zA-Z0-9_-]+)+$/', $email)) return false;
        if($this->admin->where(['email' => $email])->first()) return false;
        return true;
    }

    public function checkRegisterCompany($company)
    {
        // 5个汉字以上
        return strlen($company) >= 5*3;
    }

    public function checkRegisterRoleId($auth_id)
    {
        $roles = ct('admin_role')
            ->select('id')
            ->where(['id' => ['value' => [SUPER_ADMINISTRATOR_ROLE_ID], 'glue' => 'notin']])
            ->get();
        $tmp = [];
        foreach($roles as $k => $v) {
            $tmp[] = $v['id'];
        }
        if(in_array($auth_id, $tmp)) return true;
        return false;
    }

    public function checkRegisterPassword($password)
    {
        return strlen($password) >= 6 ? true : false;
    }

    public function changePassword($old, $new)
    {
        if(empty($old) || empty($new) || $old == $new) return false;
        if(!$this->checkRegisterPassword($new)) return false;
        if($this->encrypt($old, $this->adminInfo['salt']) == $this->adminInfo['pwd']) {
            // 盐和密码都更新
            $salt = $this->getSalt();
            return $this->admin->update($this->adminInfo['id'], [
                'salt' => $salt,
                'pwd' => $this->encrypt($new, $salt)
            ]);
        }
        return false;
    }

    public function allUsers($page, $pageSize)
    {
        return $this->admin->order('create_time desc')->limit(($page-1)*$pageSize, $pageSize)->get();
    }

    public function allUsersCount()
    {
        return $this->admin->count();
    }

    public function allApplyUsers($page, $pageSize)
    {
        return $this->admin->where(['status' => ADMIN_USER_STATUS_APPLIED])->limit(($page-1)*$pageSize, $pageSize)->get();
    }

    public function allApplyUsersCount()
    {
        return $this->admin->where(['status' => ADMIN_USER_STATUS_APPLIED])->count();
    }

    public function allNormalUsers($page, $pageSize)
    {
        return $this->admin->where(['status' => ADMIN_USER_STATUS_NORMAL])->limit(($page-1)*$pageSize, $pageSize)->get();
    }

    public function allNormalUsersCount()
    {
        return $this->admin->where(['status' => ADMIN_USER_STATUS_NORMAL])->count();
    }

    public function handleRoleApplyUsers($id, $action)
    {
        switch ($action) {

            case 'pass':
                $before = ADMIN_USER_STATUS_APPLIED;
                $after = ADMIN_USER_STATUS_NORMAL;
                $data = [
                    'uid' => $this->adminInfo['id'],
                    'type' => self::EVENT_PASS,
                    'detail' => "申请通过id: $id",
                    'create_time' => date("Y-m-d H:i:s"),
                ];
                $this -> admin_log -> insert($data,false,true);
                break;

            case 'refuse':
                $before = ADMIN_USER_STATUS_APPLIED;
                $after = ADMIN_USER_STATUS_REFUSE;
                $data = [
                    'uid' => $this->adminInfo['id'],
                    'type' => self::EVENT_REFUSE,
                    'detail' => "申请被拒id: $id",
                    'create_time' => date("Y-m-d H:i:s"),
                ];
                $this -> admin_log -> insert($data,false,true);
                break;

            case 'delete':
                $before = [
                    ADMIN_USER_STATUS_NORMAL,
                    ADMIN_USER_STATUS_APPLIED,
                    ADMIN_USER_STATUS_REFUSE,
                    ADMIN_USER_STATUS_LOCKED,
                ];
                $after = ADMIN_USER_STATUS_DELETED;
                $data = [
                    'uid' => $this->adminInfo['id'],
                    'type' => self::EVENT_DELETE,
                    'detail' => "删除账户id: $id",
                    'create_time' => date("Y-m-d H:i:s"),
                ];
                $this -> admin_log -> insert($data,false,true);
                break;

            case 'lock':
                $before = ADMIN_USER_STATUS_NORMAL;
                $after = ADMIN_USER_STATUS_LOCKED;
                $data = [
                    'uid' => $this->adminInfo['id'],
                    'type' => self::EVENT_LOCK,
                    'detail' => "锁定账户id: $id",
                    'create_time' => date("Y-m-d H:i:s"),
                ];
                $this -> admin_log -> insert($data,false,true);
                break;

            case 'unlock':
                $before = ADMIN_USER_STATUS_LOCKED;
                $after = ADMIN_USER_STATUS_NORMAL;
                $data = [
                    'uid' => $this->adminInfo['id'],
                    'type' => self::EVENT_UNLOCK,
                    'detail' => "解锁账户id: $id",
                    'create_time' => date("Y-m-d H:i:s"),
                ];
                $this -> admin_log -> insert($data,false,true);
                break;

            case 'resume':
                $before = ADMIN_USER_STATUS_DELETED;
                $after = ADMIN_USER_STATUS_NORMAL;
                $data = [
                    'uid' => $this->adminInfo['id'],
                    'type' => self::EVENT_RESUME,
                    'detail' => "恢复账户id: $id",
                    'create_time' => date("Y-m-d H:i:s"),
                ];
                $this -> admin_log -> insert($data,false,true);
                break;

            default:
                return false;
        }
        // 不支持超级管理员账户状态变动
        if($id == SUPER_ADMINISTRATOR_ROLE_ID) return false;
        return $this->admin->changeUserStatus($id, $before, $after);
    }

    public function searchUsersByUsername($username)
    {

    }

    public function createSuperAdministrator()
    {
        // 清空admin和admin_role表
        $this->admin->truncate();
        ct('admin_role')->truncate();

        $salt = $this->getSalt();

        $this->admin->insert([
            'username'  => 'admin',
            'name'      => 'admin',
            'email'     => 'admin@carkusoft.com',
            'company'   => \table_jjsan_admin::$company_array[\table_jjsan_admin::COMPANY_KEK],
            'role_id'   => 1,
            'create_time'=> date('Y-m-d H:i:s'),
            'update_time'=> date('Y-m-d H:i:s'),
            'status'     => 1,
            'salt'      => $salt,
            'pwd'       => $this->encrypt('123456', $salt),
        ]);

        ct('admin_role')->insert([
            'role'      => '超级管理员',
            'access'    => json_encode([
                'admin',
                'admin/role',
                'admin/role/add',
                'admin/role/edit',
                'admin/help',
            ], JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function getAdminUserInfo($adminId)
    {
        return $this->admin->fetch($adminId);
    }

    /**
     * 传入0个id, 返回false
     * 传入1个id, 返回true
     * 传入多个id, 角色判断
     */
    public function isTheSameRole(array $idArray)
    {
        if (empty($idArray)) return false;
        if (count($idArray) == 1) return true;
        $userInfo = $this->admin->fetch($idArray[0]);
        $count = $this->admin->where(['id' => $idArray, 'role_id' => $userInfo['role_id']])->count();
        return count($idArray) == $count;
    }

    public function updateAdminUserInfo($adminInfo)
    {
        // 修改他人的信息时
        if ($adminInfo['admin_id'] != $this->adminInfo['id']) {
            // 不能改超级管理员信息
            if ($adminInfo['role_id'] == SUPER_ADMINISTRATOR_ROLE_ID) return false;
            // 用户不存在
            $user = $this->getAdminUserInfo($adminInfo['admin_id']);
            if (!$user) return false;
            // 不能修改同角色的信息
            if ($user['role_id'] == $this->adminInfo['role_id']) return false;
        }
        // 更新信息
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_EDIT,
            'detail' => "编辑账户信息id: " . $adminInfo['admin_id'],
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this -> admin_log -> insert($data,false,true);
        return $this->admin->updateUserInfo($adminInfo);
    }

    public function system_settings($settings){
        $res = C::t('common_setting')->update('jjsan_system_settings', json_encode($settings));
        if($res){
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_SYSTEM_SETTINGS,
                'detail' => '设置全局同步策略'.json_encode($settings),
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this -> admin_log -> insert($data,false,true);
            return true;
        }else{
            return false;
        }
    }

    public function station_settings_add($settings, $name){
        $res = ct('station_settings') -> insert(array('name'=>$name, 'settings'=>json_encode($settings)));
        if ($res) {
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_STATION_SETTINGS_ADD,
                'detail' => '添加局部同步策略'.json_encode($settings),
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this -> admin_log -> insert($data,false,true);
            return true;
        } else {
            return false;
        }
    }

    public function station_settings_edit($ssid, $settings, $name){
        $res = ct('station_settings') -> update($ssid, array('name'=>$name, 'settings'=>json_encode($settings)));
        if ($res) {
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_STATION_SETTINGS_EDIT,
                'detail' => '编辑局部同步策略'.json_encode($settings),
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this -> admin_log -> insert($data,false,true);
            return true;
        } else {
            return false;
        }
    }

    public function station_settings_delete($ssid){
        $res = ct('station_settings')->update($ssid,['status'=>self::STATUS_DELETE]);
        if ($res) {
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_STATION_SETTINGS_DELETE,
                'detail' => '删除局部同步策略:'.$ssid,
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this -> admin_log -> insert($data,false,true);
            return true;
        } else {
            return false;
        }
    }

    public function global_settings($settings){
        $res = C::t('common_setting')->update('jjsan_global_settings', json_encode($settings));
        if($res){
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_GLOBAL_SETTINGS,
                'detail' => '设置客服电话'.json_encode($settings),
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this -> admin_log -> insert($data,false,true);
            return true;
        }else{
            return false;
        }
    }

    public function fee_settings($settings){
        $res = C::t('common_setting')->update('jjsan_fee_settings', json_encode($settings));
        if($res){
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_FEE_SETTINGS,
                'detail' => '设置全局收费策略'.json_encode($settings),
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this -> admin_log -> insert($data,false,true);
            return true;
        }else{
            return false;
        }
    }

    public function local_fee_settings_add($settings, $name){
        $res = ct('fee_strategy')->insert(array('name'=>$name, 'fee'=>json_encode($settings)));
        if($res){
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_LOCAL_FEE_SETTINGS_ADD,
                'detail' => '添加局部收费策略'.json_encode([$name,$settings]),
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this -> admin_log -> insert($data,false,true);
            return true;
        }else{
            return false;
        }
    }

    public function local_fee_settings_edit($settings, $fid, $name){
        $res = ct('fee_strategy')->update($fid, array('name'=>$name, 'fee'=>json_encode($settings)));
        if($res){
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_LOCAL_FEE_SETTINGS_EDIT,
                'detail' => '编辑局部收费策略'.json_encode([$name,$settings]),
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this -> admin_log -> insert($data,false,true);
            return true;
        }else{
            return false;
        }
    }

    public function local_fee_settings_delete($fid){
        $fee_info = ct('fee_strategy')->fetch($fid);
        $name = $fee_info['name'];
        $settings = $fee_info['fee'];
        $res = ct('fee_strategy')->delete($fid);
        if ($res) {
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_LOCAL_FEE_SETTINGS_DELETE,
                'detail' => '删除局部收费策略 名称: '. $name . '策略: ' .$settings,
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this -> admin_log -> insert($data,false,true);
            return true;
        } else {
            return false;
        }
    }

    public function shop_type_add($type){
        $files = FileUpload::img(UPLOAD_FILE_ROOT_DIR.'logo', UPLOAD_FILE_RELATIVE_DIR_CONTAIN_DOMAIN.'/logo');
        $files = json_encode($files);
        $res = ct('shop_type') -> insert(['type'=>$type, 'logo'=>$files], false, true);
        if ($res) {
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_SHOP_TYPE_ADD,
                'detail' => '添加商铺类型: '. $type,
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this -> admin_log -> insert($data,false,true);
            return true;
        } else {
            return false;
        }
    }

    public function shop_add(){
        $FILE_1 = $_FILES['logo'];
        $FILE_2 = $_FILES['carousels'];

        $_FILES = ['logo' => $FILE_1];
        $files = FileUpload::img(UPLOAD_FILE_ROOT_DIR.'logo', UPLOAD_FILE_RELATIVE_DIR_CONTAIN_DOMAIN.'/logo');
        $_GET['logo'] = $files;

        $_FILES = ['carousel' => $FILE_2];
        FileUpload::$files = [];
        $files = FileUpload::img(UPLOAD_FILE_ROOT_DIR.'carousel', UPLOAD_FILE_RELATIVE_DIR_CONTAIN_DOMAIN.'/carousel');
        $_GET['mats'] = $files;

        foreach($_GET['mats'] as $mat){
            $_GET['carousel'][] = $mat;
        }
        $shop = new Shop();
        if(!isset($_GET['carousel'])){
            $_GET['carousel'] = json_encode([]);
        }
        $data = array_map(function($a){
            return str_replace('：', ':', $a);
        }, $_GET);
        $res  = $shop -> add($data);

        if($res){
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_SHOP_ADD,
                'detail' => '添加商铺 id: '. $res,
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this -> admin_log -> insert($data,false,true);
            return true;
        }else{
            return false;
        }
    }

    public function shop_edit($shopid, $shop){
        $data = array_map(function($a){
            return str_replace('：', ':', $a);
        }, $_GET);
        $data_for_update[$shopid] = $data;
        $res = $shop -> update($data_for_update);
        if($res){
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_SHOP_EDIT,
                'detail' => '编辑商铺 id: '. $shopid,
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this -> admin_log -> insert($data,false,true);
            return true;
        }else{
            return false;
        }
    }

    public function logo_update($shopid){
        $files = FileUpload::img(UPLOAD_FILE_ROOT_DIR.'logo', UPLOAD_FILE_RELATIVE_DIR_CONTAIN_DOMAIN.'/logo');
        $res = ct('shop')->update($shopid, ['logo' => json_encode($files)]);
        if($res){
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_LOGO_UPDATE,
                'detail' => '更新商铺logo id: '. $shopid,
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this -> admin_log -> insert($data,false,true);
            return true;
        }else{
            return false;
        }
    }

    public function carousel_update($shopid, $shop, $mats){
        $files = FileUpload::img(UPLOAD_FILE_ROOT_DIR.'carousel', UPLOAD_FILE_RELATIVE_DIR_CONTAIN_DOMAIN.'/carousel');
        $carousel = [];
        if($mats){
            $mats = explode(',', $mats);
            foreach ($mats as $mat){
                $carousel[] = $mat;
            }
        }
        foreach ($files as $img){
            $carousel[] = $img;
        }
        $data[$shopid] = ['carousel'=>json_encode($carousel)];
        $res = $shop -> update($data);
        if($res){
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_CAROUSEL_UPDATE,
                'detail' => '更新商铺轮播图 id: '. $shopid,
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this -> admin_log -> insert($data,false,true);
            return true;
        }else{
            return false;
        }
    }

    public function cp_shop_bind($shopStation, $shop_station_id, $shopid){
        $shopStation->bindShop($shop_station_id, $shopid);
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_CP_BIND,
            'detail' => "站点绑定商铺  商铺站点id: $shop_station_id; 商铺id: $shopid ",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this -> admin_log -> insert($data,false,true);
        return true;
    }

    public function cp_shop_unbind($shopStation, $shop_station_id){
        $shopStation->unBindShop($shop_station_id);
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_CP_UNBIND,
            'detail' => "站点解绑商铺  商铺站点id: $shop_station_id",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this -> admin_log -> insert($data,false,true);
        return true;
    }

    public function cp_go_up($shopStation, $shop_station_id, $new_sid){
        $shop_station = $shopStation->fetch($shop_station_id);
        $data = ['enable' => 1];
        // 先更新lbs数据，让其在附近网点显示
        $ret = updateStationToLBS($shop_station['lbsid'], $data);
        if ($ret['errcode'] == 0) {
            // 再更新数据库，让其在后台启用，并且更新相应机器的title和address
            $res = $shopStation->update($shop_station_id, ['station_id' => $new_sid, 'status' => 1]) && ct('station')->update($new_sid, ['title' => $shop_station['title'], 'address' => $shop_station['address']]);
        }
        if($res){
            $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_CP_GO_UP,
            'detail' => "商铺站点上机  商铺站点id: $shop_station_id 站点id: $new_sid",
            'create_time' => date("Y-m-d H:i:s"),
            ];
            $this -> admin_log -> insert($data,false,true);
            return true;
        }else{
            return false;
        }
    }

    public function cp_replace($shop_station_id, $new_sid, $origin_sid){
        // 1.把这个商铺站点绑定到新的机器上
        // 2.把新机器的信息（title,address）与这个商铺站点同步
        // 3.把原来的机器信息（title,address）置空
        $res = ct('shop_station')->update($shop_station_id, ['station_id' => $new_sid]) && ct('station')->update($new_sid, ['title' => ct('shop_station')->getField($shop_station_id, 'title'), 'address' => ct('shop_station')->getField($shop_station_id, 'address')]) && ct('station')->update($origin_sid, ['title' => '', 'address' => '']);
        if($res){
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_CP_REPLACE,
                'detail' => "商铺站点换机  商铺站点id: $shop_station_id 新站点id: $new_sid 旧站点id: $origin_sid",
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this -> admin_log -> insert($data,false,true);
            return true;
        }else{
            return false;
        }
    }

    public function cp_remove(){
        // 撤机操作：将已绑定机器的商铺站点进行解绑操作
        $shop_station_id = $_GET['shop_station_id'] ? : ct('station')->getShopStationId($_GET['sid']);
        $lbsid = ct('shop_station')->getField($shop_station_id, 'lbsid');
        $sid = ct('shop_station')->getField($shop_station_id, 'station_id');
        $data = ['enable' => 0];
        // 先更新lbs数据，让其不在附近网点显示
        $ret = updateStationToLBS($lbsid, $data);
        if ($ret['errcode'] == 0) {
            // 再更新数据库，让其在后台禁用，并且清空相应机器的title和address
            $res = ct('shop_station')->update($shop_station_id, ['station_id' => 0, 'status' => 0]) && ct('station')->update($sid, ['title' => '', 'address' => '']);
        }
        if($res){
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_CP_REMOVE,
                'detail' => "商铺站点撤机  商铺站点id: $shop_station_id 站点id: $sid",
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this -> admin_log -> insert($data,false,true);
            return true;
        }else{
            return false;
        }
    }

    public function shop_station_settings($shopStation, $shop_station_id, $update_shop_station_fields){
        $res = $shopStation->setFieldsById($shop_station_id, $update_shop_station_fields);
        if($res){
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_CP_SHOP_STATION_SETTINGS ,
                'detail' => "商铺站点设置变更  商铺站点id: $shop_station_id 设置详情: ". json_encode($update_shop_station_fields),
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this -> admin_log -> insert($data,false,true);
            return true;
        }else{
            return false;
        }
    }

    public function log_slot_lock($sid, $slot_num, $all){
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_CP_SLOT_LOCK,
            'detail' => "锁住槽位 站点id: $sid; 槽位号: " . ($all? 全选 : $slot_num),
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this -> admin_log -> insert($data,false,true);
        return true;
    }

    public function log_slot_unlock($sid, $slot_num, $all){
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_CP_SLOT_UNLOCK,
            'detail' => "解锁槽位 站点id: $sid; 槽位号: " . ($all? 全选 : $slot_num),
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this -> admin_log -> insert($data,false,true);
        return true;
    }

    public function log_query_info($sid, $slot_num, $all){
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_CP_QUERY,
            'detail' => "查询槽位信息 站点id: $sid; 槽位号: " . ($all? 全选 : $slot_num),
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this -> admin_log -> insert($data,false,true);
        return true;
    }

    public function log_manually_lend($sid, $slot_num, $all){
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_CP_LEND,
            'detail' => "人工借出雨伞 站点id: $sid; 槽位号: " . ($all? 全选 : $slot_num),
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this -> admin_log -> insert($data,false,true);
        return true;
    }

    public function log_sync_umbrella($sid){
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_CP_SYNC,
            'detail' => "同步雨伞信息 站点id: $sid",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this -> admin_log -> insert($data,false,true);
        return true;
    }

    public function log_reboot($sid){
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_CP_REBOOT,
            'detail' => "人工重启设备 站点id: $sid",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this -> admin_log -> insert($data,false,true);
        return true;
    }
    public function log_module_num($sid, $module_num){
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_CP_MODULE_NUM,
            'detail' => "人工设置模组数 站点id: $sid; 模组数量: $module_num",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this -> admin_log -> insert($data,false,true);
        return true;
    }

    public function log_upgrade($sid, $file_name, $file_size){
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_CP_UPGRADE,
            'detail' => "人工升级控制 站点id: $sid; 文件名: $file_name; 文件大小: $file_size",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this -> admin_log -> insert($data,false,true);
        return true;
    }

    public function sync_strategy($station, $sid, $strategy_id){
        $res = $station->setStationSettings($sid, $strategy_id);
        if($res){
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_CP_SYNC_STRATEGY,
                'detail' => "站点设置同步策略  站点id: $sid 同步策略: $strategy_id",
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this -> admin_log -> insert($data,false,true);
            return true;
        }else{
            return false;
        }
    }

    public function log_cancel_order($order_id, $amount, $uid){
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_CANCEL_ORDER,
            'detail' => "手动撤销 订单id: $order_id; 金额: $amount; 用户id: $uid",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this -> admin_log -> insert($data,false,true);
        return true;
    }

    public function log_return_back($order_id, $amount, $uid){
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_RETURN_BACK,
            'detail' => "手动退款 订单id: $order_id; 金额: $amount; 用户id: $uid",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this -> admin_log -> insert($data,false,true);
        return true;
    }

    public function log_add_role($role){
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_ADD_ROLE,
            'detail' => "添加角色：$role",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this -> admin_log -> insert($data,false,true);
        return true;
    }

    public function log_edit_role($role_id, $role){
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_EDIT_ROLE,
            'detail' => "编辑角色：$role_id->$role",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this -> admin_log -> insert($data,false,true);
        return true;
    }

    public function log_pass_install_man($uid){
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_PASS_INSTALL_MAN,
            'detail' => "通过维护人员申请：$uid",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this -> admin_log -> insert($data,false,true);
        return true;
    }

    public function log_set_common($uid){
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_SET_COMMON,
            'detail' => "将该id设为普通人员：$uid",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this -> admin_log -> insert($data,false,true);
        return true;
    }

    public function log_set_install($uid){
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_SET_INSTALL,
            'detail' => "讲该id设为维护人员：$uid",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this -> admin_log -> insert($data,false,true);
        return true;
    }

    public function log_delete_install($uid){
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_DELETE_INSTALL,
            'detail' => "删除维护人员 user_id：$uid",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this -> admin_log -> insert($data,false,true);
        return true;
    }

    public function log_init_set($stationId)
    {
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_INIT_SET,
            'detail' => "初始化设备 机器id：$stationId",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this-> admin_log -> insert($data);
    }

    public function log_element_module_open($stationId)
    {
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_ELEMENT_MODULE_OPEN,
            'detail' => "开启设备模组 机器id：$stationId",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this-> admin_log -> insert($data);
    }

    public function log_element_module_close($stationId)
    {
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_ELEMENT_MODULE_CLOSE,
            'detail' => "关闭设备模组 机器id：$stationId",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->admin_log->insert($data);
    }

    public function log_voice_module_open($stationId)
    {
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_VOICE_MODULE_OPEN,
            'detail' => "语音功能开启 机器id：$stationId",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this-> admin_log -> insert($data);
    }

    public function log_voice_module_close($stationId)
    {
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_VOICE_MODULE_CLOSE,
            'detail' => "语音功能休眠 机器id：$stationId",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->admin_log->insert($data);
    }

    public function pictext_settings_add($settings, $name){
        $res = ct('pictext_settings')->insert(array(
            'name'=>$name,
            'pictext'=>json_encode($settings['pictext']),
            'stime'=>$settings['stime'],
            'etime'=>$settings['etime']
            )
        );
        if($res){
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_PICTEXT_SETTINGS_ADD,
                'detail' => '添加图文消息配置'.json_encode([$name,$settings]),
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this -> admin_log -> insert($data,false,true);
            return true;
        }else{
            return false;
        }
    }

    public function pictext_settings_edit($settings, $pid, $name){
        $res = ct('pictext_settings')->update($pid, array(
            'name'=>$name,
            'pictext'=>json_encode($settings['pictext']),
            'stime'=>$settings['stime'],
            'etime'=>$settings['etime']
            )
        );
        if($res){
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_PICTEXT_SETTINGS_EDIT,
                'detail' => '编辑图文消息配置'.json_encode([$name,$settings]),
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this -> admin_log -> insert($data,false,true);
            return true;
        }else{
            return false;
        }
    }

    public function pictext_settings_delete($pid){
        $pictext_info = ct('pictext_settings')->fetch($pid);
        $name = $pictext_info['name'];
        $settings = $pictext_info['pictext'];
        $res = ct('pictext_settings')->delete($pid);
        if ($res) {
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_PICTEXT_SETTINGS_DELETE,
                'detail' => '删除图文消息配置 名称: '. $name . '配置: ' .$settings,
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this -> admin_log -> insert($data,false,true);
            return true;
        } else {
            return false;
        }
    }

    public function log_add_zero_fee_user($openid)
    {
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_ADD_ZERO_FEE_USER,
            'detail' => json_encode(['openid' => $openid, 'desc' => '增加零费用用户openid'], JSON_UNESCAPED_UNICODE),
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->admin_log->insert($data);
    }

    public function log_delete_zero_fee_user($openid)
    {
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_DELETE_ZERO_FEE_USER,
            'detail' => json_encode(['openid' => $openid, 'desc' => '移除零费用用户openid'], JSON_UNESCAPED_UNICODE),
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->admin_log->insert($data);
    }
}
