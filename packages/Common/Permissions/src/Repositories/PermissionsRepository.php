<?php

namespace Common\Permissions\Repositories;

use Illuminate\Container\Container as Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Common\Permissions\Common\Common;
use Common\Permissions\Models\Authority;
use Common\Permissions\Models\Menu;
use Common\Permissions\Models\Role;
use Common\Permissions\Models\RoleAuthority;
use Prettus\Repository\Contracts\RepositoryInterface;
use Prettus\Repository\Eloquent\BaseRepository;

class PermissionsRepository extends BaseRepository implements RepositoryInterface
{
    protected $filterRoute = ['permissions/user/authority', 'permissions/department/list', 'authorized/configs', 'authorized/userList', 'permissions/department/tree', 'permissions/role/all', 'permissions/role/details', 'permissions/user/details'];
    protected $roleUserRepositoryEloquent;

    function __construct(Application $app, RoleUserRepository $roleUserRepositoryEloquent)
    {
        $this->roleUserRepositoryEloquent = $roleUserRepositoryEloquent;
        parent::__construct($app);
    }

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
    public function pass($system_id)
    {
        $user  = auth()->user();
        $route = app()->request->path();

        //如果是超级用户 直接返回true
        if (in_array($user->role_id, Role::$superRoles)) {
            return true;
        }
        //过滤不验证的路由
        if (in_array($route, $this->filterRoute) || Str::startsWith($route, 'common/')) {
            return true;
        }
        //查询用户角色当前权限
        $authorityAll = $this->checkAuthority($system_id);
        if (isset($authorityAll[$route]) && $authorityAll[$route]) {
            return true;
        }
        return false;
    }

    /**
     * 查询角色所有的权限操作
     * @param $roleIdList
     * @param string|null $system_id 当前登录子系统编号（RD0001/CRM…），为 null 时不按子系统过滤（兼容旧调用方）
     * @return array
     */
    public function getAuthority($roleIdList = [], $system_id = null)
    {
        //功能权限
        $authorityIds = RoleAuthority::whereIn('role_id', $roleIdList)->groupBy(['authority_id'])->pluck('authority_id');

        if (empty($authorityIds)) {
            return [];
        }
        $authorityIds = $authorityIds->toArray();

        $authority = Menu::select(['id', 'server_index as index', 'server_route as route'])->where('type', Menu::TYPE_AUTHORIZE);
        // 子系统隔离：只取当前子系统 + 共享（subsystem_num=''，比如系统设置）的功能权限项
        if ($system_id !== null) {
            $authority = $authority->whereIn('subsystem_num', [$system_id, '']);
        }
        $authority = $authority->get();
        $groupedMenuItems = $authority->groupBy('index');

        // 最终权限数组
        $finalAuthority = [];

        // 遍历每个分组
        foreach ($groupedMenuItems as $route => $menuItems) {
            // 检查该组中的任意一个菜单项是否在权限列表中
            $hasPermission = false;
            foreach ($menuItems as $menuItem) {
                if (in_array($menuItem->id, $authorityIds)) {
                    $hasPermission = true;
                    break;
                }
            }

            // 为该组内的所有菜单项赋予相同的权限
            foreach ($menuItems as $menuItem) {
                $finalAuthority[$menuItem->index] = $hasPermission;
                //多个权限共用情况
                if (!isset($finalAuthority[$menuItem->route]) || $finalAuthority[$menuItem->route] !== true) {
                    $finalAuthority[$menuItem->route] = $hasPermission;
                }
            }
        }
//        return ['authority_id' => $authorityIds, 'whole' => $authority];
        return $finalAuthority;
    }

    /**
     * 验证的权限
     * @param string|null $system_id 当前登录子系统编号，透传给 getAuthority 做子系统隔离
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function checkAuthority($system_id = null)
    {
        $userId     = auth()->user()->id;
        $roleIdList = $this->roleUserRepositoryEloquent->getUserToRoleIDList($userId);


        $roleIdList = array_keys(array_column($roleIdList, null, 'role_id'));

        $authority   = [];
        $authorities = $this->getAuthority($roleIdList, $system_id);
//        foreach ($authorities['whole'] as $item) {
//            $authority[$item['route']] = false;
//            if (in_array($item['id'], $authorities['authority_id'])) {
//                $authority[$item['route']] = true;
//            }
//        }
        return $authorities;
    }

    /**
     * 查询用户角色所管理的菜单和权限
     * @param string $system_number 当前登录子系统编号（RD0001=数据看板/CRM=CRM），默认 RD0001 兼容旧调用方
     * @return array
     */
    public function getRoleAuthority($system_number = 'RD0001')
    {
        $userId        = auth()->user()->id;

        $roleIdList = $this->roleUserRepositoryEloquent->getUserToRoleIDList($userId);
        $roleIdList = array_keys(array_column($roleIdList, null, 'role_id'));

        //参数添加默认值
        $data = $authority = [];
        //菜单权限
        $menu = Menu::select(['id', 'pid', 'label', 'ico', 'index', 'state', 'type', 'sort'])
            ->join('role_menus', 'role_menus.menu_id', '=', 'menus.id');

        $menu = $menu->whereIn('role_menus.role_id', $roleIdList);
        // 子系统隔离：只取当前子系统 + 共享（subsystem_num=''，比如系统设置）的菜单
        $menu = $menu->whereIn('role_menus.subsystem_num', [$system_number, '']);
        $menu = $menu->where('menus.state', '1')->where('menus.type', Menu::TYPE_MENU);
        $menu = $menu->orderBy('sort', 'DESC')->orderBy('id', 'ASC')->get()->toArray();
        $menu = array_unique($menu, SORT_REGULAR);

        //递归树状结构
        $common       = new Common();
        $data['menu'] = $common->getTree($menu);

        //功能权限
        $authority   = [];
        $authorities = $this->getAuthority($roleIdList, $system_number);

        /*foreach ($authorities['whole'] as $item) {
            $authority[$item['index']] = false;
            if (in_array($item['id'], $authorities['authority_id'])) {
                $authority[$item['index']] = true;
            }
        }*/
        $data['authority'] = $authorities;
        return $data;
    }

    /**
     * 获取第一次登录路由
     * @param string $system_number 当前登录子系统编号
     */
    public function getFirstLoginRoute($system_number = 'RD0001')
    {
        $result = $this->getRoleAuthority($system_number);
        return $result['menu'][0]['index'] ?? '';
    }
}