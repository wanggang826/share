<?php
namespace model;

use \C;

class Auth extends Model {

    public  $admin_id;
    public  $globalSearch = 0; //默认不支持全局搜索
    private $admin;
    private $current_admin;
    private $admin_role;
    private $current_admin_role;
    private $admin_city;
    private $current_admin_city;
    private $admin_shop;
    private $current_admin_shop;

    public function __construct($uid)
    {
        $this->admin_id = $uid;

        $this->admin        = ct('admin');
        $this->admin_role   = ct('admin_role');
        $this->admin_city   = ct('admin_city');
        $this->admin_shop   = ct('admin_shop');

        $this->current_admin      = $this->admin->where(['id' => $uid])->first();
        $this->current_admin_role = $this->admin_role->where(['id' => $this->current_admin['role_id']])->first();
        $this->current_admin_city = $this->admin_city->where(['admin_id' => $uid])->first();
        $this->current_admin_shop = $this->admin_shop->where(['admin_id' => $uid])->get();

        $this->globalSearch = $this->current_admin_role['global_search'];
    }

    public function getCurrentRoleName()
    {
         return $this->current_admin_role['role'];
    }

    public function getAccess()
    {
        return json_decode($this->current_admin_role['access'], 1);
    }

    public function getCity()
    {
        return json_decode($this->current_admin_city['city'], 1);
    }

    public function getAccessCities()
    {
        return $this->admin_city->getAccessCities($this->admin_id);
    }

    public function getAccessShops()
    {
        return $this->admin_shop->getAccessShops($this->admin_id);
    }

    public function getAccessCitiesByAdminId($adminId)
    {
        return $this->admin_city->getAccessCities($adminId);
    }

    public function getAccessShopsByAdminId($adminId)
    {
        return $this->admin_shop->getAccessShops($adminId);
    }

    /**
     * 获取所有的授权商铺id (包含授权的商铺id 和 授权的城市所在的商铺)
     */
    public function getAllAccessShops()
    {
        // 授权的商铺id
        $ids = $this->getAccessShops();

        // 授权的城市 所在的商铺
        $cities = $this->getAccessCities();
        $newIds = ct('shop')->getShopIdsByCities($cities);
        $ids = array_merge($ids, (array) $newIds);
        return array_unique($ids);
    }

    /**
     * 获取所有的授权商铺id (包含授权的商铺id 和 授权的城市所在的商铺)
     */
    public function getAllAccessShopsByAdminId($adminId)
    {
        // 授权的商铺id
        $ids = $this->getAccessShopsByAdminId($adminId);
        // 授权的城市 所在的商铺
        $cities = $this->getAccessCitiesByAdminId($adminId);
        $newIds = ct('shop')->getShopIdsByCities($cities);
        $ids = array_merge($ids, (array) $newIds);
        return array_unique($ids);
    }

    public function getAccessStations()
    {
        $shops = $this->getAccessShops();
        $cities = $this->getAccessCities();
        ct('shop_station')->select('station_id')->where(['shopid' => $shops])->get();
    }

    public function checkCityIsAuthorized($city)
    {
        $accessCities = $this->getAccessCities();
        if ($accessCities) {
            return in_array($city, $accessCities);
        }
        return false;
    }

    public function checkShopIdIsInAuthorizedCity($shopid)
    {
        $shopInfo = ct('shop')->where(['id' => $shopid])->first();
        if (!$shopInfo) return false;
        if (!$this->checkCityIsAuthorized($shopInfo['city'])) return false;
        return true;
    }

    /**
     * 双重检查
     * 1. 检查shopid是否在授权的商铺下面
     * 2. 检查shopid是否在授权的城市下面
     */
    public function checkShopIdIsAuthorized($shopid)
    {
        $accessShopes = $this->getAccessShops();
        if (in_array($shopid, $accessShopes) || $this->checkShopIdIsInAuthorizedCity($shopid)) return true;
        return false;

    }

    public function checkStationIdIsAuthorized($stationId)
    {
        $station = ct('shop_station')->where(['station_id' => $stationId])->first();
        if (!$station['shopid']) return false;
        return $this->checkShopIdIsAuthorized($station['shopid']);
    }

    public function checkShopStationIdIsAuthorized($shop_station_id)
    {
        $shop_station = ct('shop_station')->fetch($shop_station_id);
        return $shop_station['shopid'] ? $this->checkShopIdIsAuthorized($shop_station['shopid']) : false;
    }

    public function checkCityStatus()
    {
        if(!$this->current_admin_city) return null;
        if($this->current_admin_city['status'] == ADMIN_CITY_STATUS_APPLIED) return false;
        if($this->current_admin_city['status'] == ADMIN_CITY_STATUS_NORMAL) return true;
    }

    public function addCityAccess(array $cities)
    {
        // 没有申请过的可以申请
        if(!$this->current_admin_city) {
            return $this->admin_city->insert([
                'admin_id'  => $this->admin_id,
                'city'      => json_encode($cities, JSON_UNESCAPED_UNICODE),
                'status'    => ADMIN_CITY_STATUS_APPLIED,
                'create_time' => date('Y-m-d H:i:s'),
            ]);
        }
        return false;
    }

    public function modifyCityAccess(array $cites)
    {
        $data = $this->admin_city->select('id')->where(['admin_id' => $this->admin_id])->first();
        // 有申请记录的可以修改
        if (count($data)!==0) {
            return $this->admin_city->update($data['id'], [
                'city' => json_encode($cites, JSON_UNESCAPED_UNICODE),
                'status' => ADMIN_CITY_STATUS_APPLIED,
                'create_time' => date('Y-m-d H:i:s'),
            ]);
        }
        return false;
    }

    public function getNavAccessTree($navTree)
    {
        $access = $this->getAccess();
        $tmp = [];
        foreach($navTree as $k => $v) {
            if(in_array($k, $access)) {
                $tmp[$k]['text'] = $v['text'];
            }
            foreach($v['sub_nav'] as $kk => $vv) {
                if(in_array($k.'/'.$kk , $access)) {
                    $tmp[$k]['sub_nav'][$kk]['opt'] = $vv['opt'];
                }
                if (isset($vv['do']) && $vv['do']) {
                    foreach($vv['do'] as $kkk => $vvv) {
                        if(in_array($k.'/'.$kk.'/'.$kkk, $access)) {
                            $tmp[$k]['sub_nav'][$kk]['do'][$kkk] = $vvv;
                        }

                    }
                }
            }
        }
        return $tmp;
    }

    public function allCanRegisterRoles()
    {
        return $this->admin_role->where(['id' => ['value' => [SUPER_ADMINISTRATOR_ROLE_ID], 'glue' => 'notin']])->get();
    }

    public function allRoles()
    {
        return $this->admin_role->get();
    }

    public function isAuthorizedUrl($act, $opt, $do = '',$tree = [])
    {
        if(array_key_exists($opt,$tree[$act]['sub_nav'])){
            if(!in_array($act.'/'.$opt, $this->getAccess())) {
                return false;
            }elseif($do && !in_array($act.'/'.$opt.'/'.$do, $this->getAccess())) {
                return false;
            }
        // 不在权限控制内的 url 直接给过
        }
        return true;
    }

    public function isAuthorizedCity($cities, $cities_tree)
    {
        // 省市验证: 有城市必有省份
        $province = array_map(function($v){
            if(strpos($v, '/') == 0) {
                return $v;
            }
        }, $cities);
        foreach($cities as $v) {
            if(strpos($v, '/')) {
                $p = substr($v, 0, strpos($v, '/'));
                if(!in_array($p, $province)) {
                    return false;
                }
            }
        }

        $tmp = [];
        foreach($cities_tree as $v) {
            $tmp[] = $v['province'];
            foreach($v['city'] as $vv) {
                $tmp[] = $v['province'].'/'.$vv['name'];
            }
        }
        //求2个数组的并集,并集数组的个数等于待验证的数组个数时,验证通过.
        return count(array_intersect($cities, $tmp)) == count($cities);
    }

    public function allCitesAccessCount()
    {
        return $this->admin_city->count();
    }

    public function applyCitesInfo($page, $pageSize)
    {
        //判断那些人申请了city权限
        $admin_cities = $this->admin_city->limit(($page-1)*$pageSize, $pageSize)->order('status asc')->get();
        foreach($admin_cities as $v) {
            $admin_ids[] = $v['admin_id'];
        }

        //这些人对应的相关注册信息
        $admin = $this->admin->where(['id' => $admin_ids])->get();
        foreach($admin as $v) {
            $role_ids[] = $v['role_id'];
        }
        //这些人对应的角色
        $admin_roles = $this->admin_role->where(['id' => $role_ids])->get();

        //整合数据
        $info = [];
        foreach($admin_cities as $admin_cities_k => $admin_cities_v) {
            foreach($admin as $admin_v) {
                if($admin_cities_v['admin_id'] == $admin_v['id']) {
                    $info[$admin_cities_k] = [
                        'id'            => $admin_cities_v['id'],
                        'admin_id'      => $admin_cities_v['admin_id'],
                        'city'          => $admin_cities_v['city'],
                        'status'        => $admin_cities_v['status'],
                        'name'          => $admin_v['name'],
                        'create_time'   => $admin_v['create_time'],
                        'company'       => $admin_v['company'],
                        'role_id'       => $admin_v['role_id'],
                    ];
                }
            }
        }
        foreach($info as &$v) {
            foreach($admin_roles as $admin_roles_v) {
                if($v['role_id'] == $admin_roles_v['id']) {
                    $v['role'] = $admin_roles_v['role'];
                }
            }
        }
        return $info;
    }

    public function allApplyInfos()
    {
        // 所有申请状态的城市
        $sql  = '';
        $sql .= '(SELECT * FROM %t WHERE status = 0) UNION (SELECT * FROM %t WHERE status = 0) order by admin_id asc';
        return \DB::fetch_all($sql, [ 'jjsan_admin_shop','jjsan_admin_city' ]);
    }
    
    public function handleCityApplyUsers($id, $action)
    {
        switch ($action) {
            case 'pass':
                $before = ADMIN_CITY_STATUS_APPLIED;
                $after  = ADMIN_CITY_STATUS_NORMAL;
                break;

            default:
                return false;
        }
        return $this->admin_city->changeCityStatus($id, $before, $after);
    }

    public function handleShopApplyUsers($admin_shop_id, $action)
    {
        switch ($action) {
            case 'pass':
                $before = \table_jjsan_admin_shop::STATUS_APPLY;
                $after  = \table_jjsan_admin_shop::STATUS_PASS;
                break;

            default:
                return false;
        }
        return $this->admin_shop->changeShopStatus($admin_shop_id, $before, $after);
    }

    public function handleAllShopsApplyByAdminId($admin_id, $action)
    {
        switch ($action) {
            case 'pass':
                $before = \table_jjsan_admin_shop::STATUS_APPLY;
                $after  = \table_jjsan_admin_shop::STATUS_PASS;
                break;

            default:
                return false;
        }
        return $this->admin_shop->changeAllShopsStatus($admin_id, $before, $after);
    }

    public function deleteCurrentCitesAccess()
    {
        if(!$this->current_admin_city) return false;
        return $this->admin_city->delete($this->current_admin_city['id']);
    }

    public function createNewRole($role, $access, $global_search)
    {
        if(empty($role) || empty($access) || !is_string($role) || !is_array($access)) return false;
        if($this->admin_role->where(['role' => $role])->count()) return false;
        return $this->admin_role->insert([
            'role'          => $role,
            'access'        => json_encode($access, JSON_UNESCAPED_UNICODE),
            'global_search' => $global_search && 1,
            'create_time'   => date('Y-m-d H:i:s')
        ]);
    }

    public function updateRoleAccess($role_id, $role, $access, $global_search)
    {
        if (empty($role)) return false;
        return $this->admin_role->update($role_id, [
            'role'          => $role,
            'access'        => json_encode($access, JSON_UNESCAPED_UNICODE),
            'global_search' => $global_search && 1,
        ]);
    }

    public function isAuthorizedAction($access_array, $access_tree)
    {
        // act/opt/do 有子级必有父级
        foreach($access_array as $v) {
            $cnt = substr_count($v, '/');
            if ($cnt == 0) {
                continue;
            } elseif ($cnt == 1) {
                if (!in_array(substr($v, 0, strpos($v, '/')), $access_array)) return false;
            } elseif ($cnt == 2) {
                if (!in_array(substr($v, 0, strrpos($v, '/')), $access_array)) return false;
            } else {
                return false;
            }
        }

        // act/opt/do 必须在tree里面
        foreach($access_array as $v) {
            $cnt = substr_count($v, '/');
            if ($cnt == 0) {
                if (!key_exists($v, $access_tree)) return false;
            } elseif ($cnt == 1) {
                list($act, $opt) = explode('/', $v);
                if (empty($access_tree[$act]['sub_nav'][$opt])) return false;
            } elseif ($cnt == 2) {
                list($act, $opt, $do) = explode('/', $v);
                if (empty($access_tree[$act]['sub_nav'][$opt]['do'][$do])) return false;
            } else {
                return false;
            }
        }

        return true;
    }

    public function getAllRoles()
    {
        return $this->admin_role->get();
    }

    public function getAllRolesExceptSuperAdministrator()
    {
        return $this->admin_role->where(['id' => ['value' => [SUPER_ADMINISTRATOR_ROLE_ID], 'glue' => 'notin']])->get();
    }

    public function getRoleInfo($role_id)
    {
        return $this->admin_role->where(['id' => $role_id])->first();
    }

    public function addShopAccess($shop_id)
    {
        if (empty($shop_id)) return false;
        $rst = $this->admin_shop->where(['shop_id' => $shop_id])->first();
        if($rst) return false;
        $data = [
            'admin_id'      => $this->admin_id,
            'shop_id'       => 0,
            'status'        => \table_jjsan_admin_shop::STATUS_APPLY,
            'create_time'   => date('Y-m-d H:i:s'),
        ];
        if (!is_array($shop_id)) {
            $shop_id = [$shop_id];
        }
        // 去重
        $shop_id = array_unique($shop_id);
        // 去零，去负数，去非整型
        $shop_id = array_filter($shop_id, function($a){
           return is_numeric($a) && $a > 0;
        });
        if (empty($shop_id)) return false;
        // 验证shop_id是否合法
        if (count($shop_id) != ct('shop')->where(['id' => $shop_id])->count()) return false;
        // 批量插入
        foreach ($shop_id as $v) {
            $data['shop_id'] = $v;
            $datas[] = $data;
        }
        return $this->admin_shop->batchInsert($datas);
    }

    public function deleteShopAccess($admin_shop_id)
    {
        $rst = $this->admin_shop->where(['admin_id' => $this->admin_id, 'id' => $admin_shop_id])->first();
        if(!$rst) return false;
        return $this->admin_shop->delete($admin_shop_id);
    }

    public function deleteShopAccessByAdminIdAndShopId($adminId, $shopId)
    {
        return $this->admin_shop->deleteAppliedShopId($adminId, $shopId);
    }

    public function deleteCityAccessByAdminIdAndCityName($adminId, $cityName)
    {
        return $this->admin_city->deleteAppliedCityName($adminId, $cityName);
    }

    public function getCurrentApplyCites()
    {
        return $this->admin_shop->where(['admin_id' => $this->admin_id])->get();
    }

    public function allShopsAccessCount()
    {
        return $this->admin_shop->count();
    }

    public function applyShopsInfo($page = 1, $pageSize = 10)
    {
        return $this->admin_shop->order('status asc, id desc')->limit(($page-1)*$pageSize, $pageSize)->get();
    }

    public function checkGlobalSearchByAdminId($adminId)
    {
        $admin = $this->admin->fetch($adminId);
        $adminRole = $this->admin_role->fetch($admin['role_id']);
        return $adminRole['global_search'];
    }
}
