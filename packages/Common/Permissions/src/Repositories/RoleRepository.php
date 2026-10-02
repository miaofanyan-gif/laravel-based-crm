<?php

namespace Common\Permissions\Repositories;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Common\Permissions\Models\Authority;
use Common\Permissions\Models\Menu;
use Common\Permissions\Models\Role;
use Common\Exceptions\RepositoryException;
use Common\Libs\Common;
use Common\Permissions\Models\RoleAuthority;
use Common\Permissions\Models\RoleMenu;
use Common\Permissions\Models\RoleUser;
use Common\Permissions\Models\User;
use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Criteria\RequestCriteria;
use Illuminate\Container\Container;
use Prettus\Repository\Contracts\RepositoryInterface;

/**
 * Class RoleRepository.
 * @package namespace App\Repositories;
 */
class RoleRepository extends BaseRepository implements RepositoryInterface
{

    private $userRepository;
    protected $filterRoute = ['user/authority', 'department/list'];
    private $roleUserRepositoryEloquent;
    private $menuRepositoryEloquent;

    function __construct(Container              $app,
                         UsersRepository        $userRepository,
                         RoleUserRepository     $roleUserRepositoryEloquent,
                         MenuRepositoryEloquent $menuRepositoryEloquent
    )
    {
        $this->userRepository             = $userRepository;
        $this->menuRepositoryEloquent     = $menuRepositoryEloquent;
        $this->roleUserRepositoryEloquent = $roleUserRepositoryEloquent;
        parent::__construct($app);
    }

    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return Role::class;
    }

    /**
     * 权限验证
     * @return bool
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function pass()
    {
        $user  = auth()->user();
        $route = app()->request->path();

        $authority = Authority::where('route', $route)->first();
        if (!$authority) {
            $inserData['label']  = request()->path();
            $inserData['method'] = request()->method();
            $inserData['route']  = request()->path();
            $inserData['index']  = str_replace('/', '_', request()->path());
            Authority::insert($inserData);
        }

        //如果是超级用户 直接返回true
        if (in_array($user->role_id, Role::$superRoles)) {
            return true;
        }
        //过滤不验证的路由
        if (in_array($route, $this->filterRoute)) {
            return true;
        }
        //查询用户角色当前权限
        $authorityAll = $this->checkAuthority();
        if (isset($authorityAll[$route]) && $authorityAll[$route]) {
            return true;
        }
        return false;
    }

    /**
     * 查询角色小所有的权限操作
     * @param $role_ids
     * @return mixed
     */
    protected function getAuthority($role_ids)
    {
        //功能权限
        return Authority::select(['index', 'route', 'authority_id'])
            ->leftjoin('role_authorities', function ($join) use ($role_ids) {
                $join->on('role_authorities.authority_id', '=', 'authorities.id')
                    ->whereIn('role_authorities.role_id', $role_ids);
            });
    }


    /**
     * 验证的权限
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function checkAuthority()
    {
        $userInfo   = auth()->user()->toArray();
        $roleIds    = ($userInfo['role_id'] == 1) ? [1] : array_column($userInfo['role'], 'id');
        $cache_name = 'role_authorities_' . implode(',', $roleIds);//用户首次进入才查询权限
        if (Cache::has($cache_name)) {
            return Cache::get($cache_name);
        }
        $authority   = [];
        $authorities = $this->getAuthority($roleIds);
        $authorities->get()->each(function ($item) use (&$authority) {
            $authority[$item->route] = $item->authority_id ? true : false;
        });
        Cache::put($cache_name, $authority);//存入缓存
        return $authority;
    }

    /**
     * 查询用户角色所管理的菜单和权限
     * @param $system_number
     * @return array
     */
    public function getRoleAuthority($system_number = '')
    {
        $system_number = $system_number ?: 'RD0001';
        $userInfo   = auth()->user()->load(['role','departments'])->toArray();
        $roleIds    = ($userInfo['role_id'] == 1) ? [1] : array_column($userInfo['role'], 'id');
        // 缓存 key 必须带子系统编号，否则切换子系统会命中另一个子系统缓存下的菜单（24小时内）
        $cache_name = 'login_token_' . implode(',', $roleIds) . '_' . $system_number;
        if (Cache::has($cache_name)) {
            return Cache::get($cache_name);
        }
        //参数添加默认值
        $data = $authority = [];
        //菜单权限
        $menuQuery = Menu::select(['id', 'pid', 'label', 'ico', 'index', 'state', 'type', 'sort'])
            ->join('role_menus', 'role_menus.menu_id', '=', 'menus.id');
        $menu      = $menuQuery->whereIn('role_menus.role_id', $roleIds)
            // 子系统隔离：只取当前子系统 + 共享（subsystem_num=''，比如系统设置）的菜单
            ->whereIn('role_menus.subsystem_num', [$system_number, ''])
            ->where('menus.state', '1');
        $menu = $menu->orderBy('sort', 'DESC')->orderBy('id', 'ASC')->get()->toArray();
        //递归树状结构
        $common       = new Common();
        $data['menu'] = $common->getTree($menu);
        //功能权限
//        $authority   = [];
//        $authorities = $this->getAuthority($roleIds);
//
//        $authorities->get()->each(function ($item) use (&$authority) {
//            $authority[$item->index] = $item->authority_id ? true : false;
//        });
//        $data['authority'] = $authority;
        Cache::put($cache_name, $data, 86400);//存入缓存
        return $data;
    }

    /**
     * 获取第一次登录路由
     * @param $system_number
     * @return mixed|string
     */
    public function getFirstLoginRoute($system_number = '')
    {
        $result = $this->getRoleAuthority($system_number);
        return $this->getRoutePath($result['menu'][0] ?? '');
    }

    private function getRoutePath($route) {
        if (!empty($route['children'][0]['children'][0]['index'])) {
            return '/' . $route['children'][0]['children'][0]['index'];
        } elseif (!empty($route['children'][0]['index'])) {
            return '/' . $route['children'][0]['index'];
        } elseif (!empty($route['index'])) {
            return '/' . $route['index'];
        } else {
            return '/';
        }
    }

    /**
     * 角色列表
     * @param $data
     * @return mixed
     */
    public function getList($data)
    {
        $limit  = $data['limit'] ?? 10;
        $fileds = $this->getModel()->listFields;
        $query  = $this->model->select($fileds);

        if (!empty($data['name'])) {
            $query = $query->where('name', 'like', '%' . $data['name'] . '%');
        }

        $result = $query->orderBy('id', 'DESC')->paginate($limit)->toArray();
        if (!empty($result['data'])) {
            $all_roles = $this->pluck('name', 'id')->toArray();
            $roleIds   = Arr::pluck($result['data'], 'id');

            $roleUserCounts = $this->roleUserRepositoryEloquent
                ->select('role_id', \DB::raw('count(user_id) as user_count'))
                ->whereIn('role_id', $roleIds)
                ->groupBy('role_id')
                ->get()
                ->keyBy('role_id')
                ->map(function ($item) {
                    return $item['user_count'];
                })
                ->toArray();

            $role_type = config('media.role_type');
            foreach ($result['data'] as &$item) {
                if(empty($item['data_permission']['department_ids'])){
                    $item['data_permission']['department_ids'] = [];
                }else{
                    $item['data_permission']['department_ids'] = [];
                }
                if(empty($item['data_permission']['permission_ids'])){
                    $item['data_permission']['permission_ids'] = [];
                }else{
                    $item['data_permission']['permission_ids'] = User::query()->select(['id','name','area'])->whereIn('id', $item['data_permission']['permission_ids'])->get();
                }
                $item['index_name'] = $role_type[$item['index']] ?? '';
                $item['parent_name'] = $all_roles[$item['parent_id']] ?? '-';
                $item['user_count']  = $roleUserCounts[$item['id']] ?? 0;
            }
        }

        return $result;

    }

    /**
     * 角色详情
     * @param $data
     * @return mixed
     * @throws RepositoryException
     */
    public function detailedInformation($data)
    {
        //判断数据是否存在
        $this->isExist($data['id']);
        //查询数据
        $data = $this->select(['id', 'name', 'index', 'parent_id', 'select_menu', 'subsystem_select', 'media_ids', 'data_permission'])->where(['id' => $data['id']])->first();
        if (!empty($data)) {
            $data = $data->toArray();
            $data['select_menu']      = json_decode($data['select_menu'], true) ?: [];
            $subsystem_selects        = json_decode($data['subsystem_select'], true) ?: [];
            $data['subsystem_select'] = $subsystem_selects;
            if(!is_null($data['data_permission'])) {
                if(empty($data['data_permission']['department_ids'])){
                    $data['data_permission']['department_ids'] = [];

                }else{
                    $data['data_permission']['department_ids'] = [];
                }
                if(empty($data['data_permission']['permission_ids'])){
                    $data['data_permission']['permission_ids'] = [];
                }else{
                    $data['data_permission']['permission_ids'] = User::query()->select(['id','name','area'])->whereIn('id', $data['data_permission']['permission_ids'])->get();
                }
            }
        }
        
//        $data['menu_ids'] = array_merge($menuIds, $authorizeIds);
        return $data;
    }


    /**
     * 新增角色
     * @param $data
     * @return true
     * @throws \Common\Permissions\Common\RepositoryException
     */
    public function addRole($data)
    {
        $row = Role::where('name', $data['name'])->first();
        if ($row) {
            throw new \Common\Permissions\Common\RepositoryException('角色名称不能重复或已经存在', 21260);
        }

        $addData = [
            'name'            => $data['name'],
            'desc'            => $data['desc'],
            'parent_id'       => $data['parent_id'],
            'created_at'      => date('Y-m-d H:i:s')
        ];
        if ($this->insert($addData)) {
            return true;
        } else
            throw new \Common\Permissions\Common\RepositoryException('角色添加失败', 21250);
    }


    /**
     * 修改角色
     * @param $data
     * @return true
     * @throws RepositoryException
     */
    public function updateRole($data)
    {
        //判断数据是否存在
        $this->isExist($data['id']);

        $updateData = [
            'name'            => $data['name'],
            'desc'            => $data['desc'] ?? '',
            'parent_id'       => $data['parent_id'],
        ];
        //进行数据修改
        if ($this->where(['id' => $data['id']])->update($updateData)) {
            return true;
        } else
            throw new RepositoryException('角色修改失败', 21251);
    }


    /**
     * 删除角色
     * @param $data
     * @return true
     * @throws RepositoryException
     */
    public function deleteRole($data)
    {
        //判断数据是否存在
        $this->isExist($data['id']);
        //进行数据删除
        if ($this->where(['id' => $data['id']])->delete()) {
            return true;
        } else
            throw new RepositoryException('角色删除失败', 21252);
    }

    /**
     * 设置角色权限-用户中心新版权限设置
     * @param $data
     * @return true
     * @throws RepositoryException
     */
    public function setPermissionNew($data)
    {
        $info = $this->isExist($data['id']);
        DB::beginTransaction();
        try {
            $subsystemSelect = is_array($info->subsystem_select) ? $info->subsystem_select : (json_decode($info->subsystem_select ?? '[]', true) ?: []);

            $roleMenuData = [];
            $roleAuthorityData = [];

            foreach ($data['permissionData'] as $permissionDatum){
                $system_num = data_get($permissionDatum, 'system_num');
                $authorize_menu_ids = data_get($permissionDatum, 'authorize_menu_ids', []);

                // 空字符串是"系统设置"共享子系统的合法标识（subsystem_num=''），不能当成
                // "没传"拒绝——用 empty() 之前把这一批全部提交都会失败，因为 checkedMenuList
                // 现在固定包含 RD0001/CRM/'' 三个 system_num，''这一项永远会命中 empty()
                if ($system_num === null) {
                    throw new RepositoryException('缺少系统标识，请检查', 10001);
                }

                $subsystemSelect[$system_num] = $authorize_menu_ids;

                $allIds = $this->getParentsById($authorize_menu_ids);
                $menus = $this->menuRepositoryEloquent
                    ->whereIn('id', $allIds)
                    ->whereIn('type', [1, 2])
                    ->where('subsystem_num', $system_num)
                    ->get()
                    ->groupBy('type');


                // 插入role_menu  role_authorize
                foreach ($menus->get(1, collect()) as $m) {
                    $roleMenuData[] = [
                        'role_id'       => $data['id'],
                        'menu_id'       => $m->id,
                        'subsystem_num' => $system_num,
                    ];
                }
                // type = 2 → role_authority
                foreach ($menus->get(2, collect()) as $a) {
                    $roleAuthorityData[] = [
                        'role_id'        => $data['id'],
                        'authority_id'   => $a->id,
                        'subsystem_num'  => $system_num,
                    ];
                }
            }

            // 更新角色表的菜单字段
            $info->subsystem_select=$subsystemSelect;
            $info->save();

            // 删除旧数据关联
            RoleMenu::where('role_id', $info->id)->delete();
            RoleAuthority::where('role_id', $info->id)->delete();


            //  批量插入新关联
            if (! empty($roleMenuData)) {
                RoleMenu::insert($roleMenuData);
            }
            if (! empty($roleAuthorityData)) {
                RoleAuthority::insert($roleAuthorityData);
            }

            DB::commit();
            return true;
        } catch (\Exception $exception) {
            $errMsg = $exception->getMessage().$exception->getFile().$exception->getLine();
            Log::channel('user_info')->warning("设置权限失败".$errMsg, $data);
            DB::rollBack();
            throw new RepositoryException('权限设置失败', 21259);
        }
    }

    /**
     * 设置角色权限
     * @param $data
     * @return true
     * @throws RepositoryException
     */
    public function setPermission($data)
    {
        $this->isExist($data['id']);
        DB::beginTransaction();
        try {

            $system_number = $data['system_number'] ?? '';
            //把authorize_menu_ids更新角色上的字段方便前端渲染
            $update_select = ['select_menu' => json_encode($data['authorize_menu_ids'])];
            if ($system_number != 'RD0001') {
                $update_select = ['subsystem_select' => json_encode([$system_number => $data['authorize_menu_ids']])];
            }
            $this->where(['id' => $data['id']])->update($update_select);

            //查找选中菜单的父级菜单ID
            $newAuthIds = $this->getParentsById($data['authorize_menu_ids']);

            //区分菜单 和权限ID
            $menuInfos = $this->menuRepositoryEloquent->select(['id', 'type'])->whereIn('id', $newAuthIds)
                ->whereIn('type', [1, 2]) // 筛选 type 为 1 或 2 的记录
                ->where('subsystem_num', $system_number)
                ->get();
            $groupIds  = $menuInfos->groupBy('type');

            $menuIds      = $groupIds->get(1, collect())->pluck('id')->toArray(); // 获取 type = 1 的菜单 ID
            $authorityIds = $groupIds->get(2, collect())->pluck('id')->toArray(); // 获取 type = 2 的权限


            //删除之前表数据
            RoleMenu::where('role_id', $data['id'])->where('subsystem_num', $system_number)->delete();
            RoleAuthority::where('role_id', $data['id'])->where('subsystem_num', $system_number)->delete();

            //插入role_menu表
            if (!empty($menuIds)) {
                $roleMenuData = [];
                collect($menuIds)->each(function ($menu_id) use ($data, &$roleMenuData, $system_number) {
                    $roleMenuData[] = ['role_id' => $data['id'], 'menu_id' => $menu_id, 'subsystem_num' => $system_number];
                });
                RoleMenu::insert($roleMenuData);
            }

            if (!empty($authorityIds)) {
                //插入role_authorities表
                $roleAuthorityData = [];
                collect($authorityIds)->each(function ($authority_id) use ($data, &$roleAuthorityData, $system_number) {
                    $roleAuthorityData[] = ['role_id' => $data['id'], 'authority_id' => $authority_id, 'subsystem_num' => $system_number];
                });
                RoleAuthority::insert($roleAuthorityData);
            }


            DB::commit();
            return true;
        } catch (\Exception $exception) {
            DB::rollBack();
            throw new RepositoryException('权限设置失败', 21259);
        }
    }

    /**
     * 设置数据权限
     * @param $data
     * @return mixed
     * @throws RepositoryException
     */
    public function setDataPermission($data)
    {
        $this->isExist($data['id']);

        DB::beginTransaction();
        try {
            $update_data = [
                'index'           => $data['index'],
                'media_ids'       => $data['media_ids'] ?? '',
                'data_permission' => json_encode($data['data_permission']),
                'updated_at'      => date('Y-m-d H:i:s')
            ];
            DB::commit();
            return $this->getModel()->where('id', $data['id'])->update($update_data);
        }catch (\Exception $exception){
            DB::rollBack();
            throw new RepositoryException('权限设置失败', 21259);
        }
    }

    /**
     * 查找菜单的所有父级ID
     * @param $ids
     * @return array
     */
    public function getParentsById($ids)
    {
        $allIds    = $ids;
        $parentIds = [];

        while (!empty($ids)) {
            $parentIds = $this->menuRepositoryEloquent
                ->whereIn('id', $ids)->where('pid', '<>', 0)
                ->pluck('pid')
                ->toArray();

            $ids = array_filter($parentIds, function ($pid) {
                return $pid > 0;
            });

            $allIds = array_merge($allIds, $parentIds);
        }

        return array_unique($allIds);
    }

    /**
     * 获取所有角色
     * @return array
     */
    public function getAllRole()
    {
        // 获取当前登录人角色管理的下级角色数据
        $userInfo        = auth()->user()->toArray();

        // 查找当前登录人角色管理的子角色
        $subRoleIds = [];
        if($userInfo['role_id'] != 1){
            $manage_role_ids = array_column($userInfo['role'], 'manage_role_ids');
            if (empty($manage_role_ids)) {
                return [];
            }else{
                foreach ($manage_role_ids as $manage_role_id){
                    $subRoleIds = array_unique(array_merge($manage_role_id, $subRoleIds));
                }
            }
            if(empty($subRoleIds)){
                return [];
            }
        }

        $model = $this->getModel();
        if(!empty($subRoleIds)){
            $model = $model->whereIn('id', $subRoleIds);
        }

        return $model->select(['id as value', 'name as label'])->where(['state' => 1])->orderBy('id', 'DESC')->get()->toArray();
    }

    /**
     * 是否有数据
     */
    public function isExist($id)
    {
        if (!$info = $this->where(['id' => $id])->first())
            throw new RepositoryException('数据不存在', 21123);
        return $info;
    }

    /**
     * Boot up the repository, pushing criteria
     */
    public function boot()
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }

}
