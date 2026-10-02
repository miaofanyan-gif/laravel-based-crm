<?php

namespace Common\Permissions\Repositories;
/**
 * Class UserRepository
 *
 * @package namespace App\Repositories;
 */

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Request as RequestFacade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Common\Libs\Common;
use Common\Permissions\Models\AreaUser;
use Common\Permissions\Models\Department;
use Common\Permissions\Models\DepartmentsUsers;
use Common\Permissions\Models\Menu;
use Common\Permissions\Models\Role;
use Common\Permissions\Models\RoleMenu;
use Common\Permissions\Models\RoleUser;
use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Criteria\RequestCriteria;
use Common\Permissions\Models\User;
use Common\Permissions\Common\RepositoryException;
use Prettus\Repository\Contracts\RepositoryInterface;
use Illuminate\Support\Facades\Redis;

use Illuminate\Container\Container;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class UsersRepository extends BaseRepository implements RepositoryInterface
{

    protected $jwt;
    protected $departmentRepository;
    public $jtiKeyPrefix;
    protected $departmentsUsersRepository;

    public function __construct(Container $app, JWTAuth $jwt, DepartmentRepository $departmentRepository, DepartmentUserRepository $departmentsUsersRepository)
    {
        $this->departmentRepository       = $departmentRepository;
        $this->departmentsUsersRepository = $departmentsUsersRepository;
        $this->jwt                        = $jwt;
        $this->jtiKeyPrefix               = config('permissions.jtiKeyPrefix');
        parent::__construct($app);
    }

    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return User::class;
    }

    /**
     * token校验
     * @return bool
     */
    public function checkToken()
    {
        try {

//            if ((new \Illuminate\Contracts\Auth\Factory())->guard('web')->guest()) {
//
//                return $this->ResponseFail(401);
//            }

            // 检查用户是否登录
            $tokenObj = JWTAuth::parseToken();
            if ($this->isTokenBlacklisted()) {
                return false;
            }
            $token = $tokenObj->getToken();

            JWTAuth::setToken($token);
            $user = $tokenObj->authenticate();
            if ($user) {
                if (in_array($user->status, [-1, 0, 2]) || $user->trashed()) {
                    return false;
                }
            }

            // 获取Token ID (jti claim)
            $payload = JWTAuth::getPayload($token)->toArray();
//            Log::info('获取Token ID (jti claim)', ['message' => $payload]);
            $tokenId = $payload['jti'] ?? null; // 从数组中获取jti
//            Log::info('从数组中获取jti:', ['message' => $tokenId]);
            // 检查Token是否在Redis中
            $jti_token_key = $this->jtiKeyPrefix . ":{$user->id}:" . $tokenId;
            if (!Redis::connection('permission_cache')->exists($jti_token_key)) {
                return false;
            }
            return true;
        } catch (TokenBlacklistedException $exception) {
            Log::channel('user_info')->info('token in blacklist', ['error' => 'could_not_create_token', 'message' => $exception->getMessage()]);
            return false;
        } catch (TokenInvalidException $e) {
            return false;
        } catch (\Exception $e) {
            // 处理其他异常情况
            Log::channel('user_info')->info("perssions令牌校验，令牌无效", ['error' => 'could_not_create_token', 'message' => $e->getMessage()]);
            return false;
        }
    }


    /**
     * jwt 黑名单监测
     * @return mixed
     */
    public function isTokenBlacklisted()
    {
        $token   = JWTAuth::getToken();
        $payload = JWTAuth::manager()->decode($token);

        return JWTAuth::manager()->getBlacklist()->has($payload);

    }

    /**
     * 登录数据更新
     */
    public function loginSuccess($user)
    {
        try {
            $user->last_login = date("Y-m-d H:i:s");
            $user->last_ip    = RequestFacade::header('x-real-ip',RequestFacade::ip());
            $this->getModel()->where('id', $user->id)->update(['last_login' => $user->last_login, 'last_ip' => $user->last_ip]);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 生成密码
     * @param $password
     * @return mixed
     */
    public function generatePassword($password)
    {
        return auth()->getProvider()->getHasher()->make(trim($password));
    }

    /**
     * 用户信息
     * @param $userId
     * @param $is_refresh
     * @return array|mixed
     * @throws \Exception
     */
    public function userDetail($userId = null, $is_refresh = true)//todo
    {
        $user_id   = is_null($userId) ? auth()->user()->id : $userId;
        $cache_key = config('permissions.cache_user_detail_key');
        $info      = Redis::connection('permission_cache')->hget($cache_key, $user_id);
        if (!is_null($info) && !$is_refresh) {
            return json_decode($info, true);
        }

        $field = $this->getModel()->listFields;
        $query = $this->select($field);
        //根据id排序
        $query->orderBy('id', 'DESC');
        $result = $query->where(['id' => $user_id])->first();

        if (!$result) {
            return [];
        }
        $result->load(['role', 'departments']);

        $result                 = $result->toArray();
        $dateTime               = new \DateTime($result['created_at']);
        $formattedDateTime      = $dateTime->format('Y-m-d H:i:s');
        $result['created_time'] = $formattedDateTime;
        $result['role_name']    = implode('、', array_column($result['role'], 'name'));

        // 获取当前登录人身上绑定的业务部门
        // 部门信息
        $departs_info                    = collect($result['departments'])->whereIn('tag', [1, 2, 3, 4, 5, 6])->first();
        $result['businessDeparmentId']   = $departs_info['id'] ?? 0;//当前业务部门
        $result['businessDeparmentName'] = $departs_info['name'] ?? '';

        //查询当前人所在部门的领导  组长信息
        $current_depart       = collect($result['departments'])->first();
        $manageInfo           = $this->getDepartLeader($current_depart['dep_id'] ?? 0);
        $result['manageId']   = $manageInfo->id ?? 0; //组长
        $result['manageName'] = $manageInfo->name ?? '';


        // 总监信息
        $director_info = [];
        $director_info = $this->findParentDepartment($current_depart['dep_pid'] ?? 0, $director_info);
        $director_id   = 0;
        $director_name = '';
        if (!empty($director_info)) {
            $userRow       = $this->getDepartLeader($director_info['dep_id'] ?? 0);
            $director_id   = $userRow->id ?? 0;
            $director_name = $userRow->name ?? '';
        }
        $result['directorId']   = $director_id;//总监
        $result['directorName'] = $director_name;

        //数据权限
        $result['data_permission'] = $this->getUserPermissions($result['data_permission'], $result);

        // 获取当前登录人是部门总监或主管下的运营+销售 -数据看板
        $saleAndOptimizeIds = ['sale_user_ids' => [], 'optimize_user_ids' => []];

        $sale_depart_ids   = $this->departmentRepository->getManageGroupIds($result['id'], 1);
        $optimize_user_ids = $this->departmentRepository->getManageGroupIds($result['id'], 2);

        $sale_user_ids                           = DepartmentsUsers::query()->leftJoin('users', 'departments_users.userid', 'users.userid')->whereIn('departments_users.department_id', $sale_depart_ids)->where('users.status', 1)->pluck('users.id')->toArray();
        $optimize_user_ids                       = DepartmentsUsers::query()->leftJoin('users', 'departments_users.userid', 'users.userid')->whereIn('departments_users.department_id', $optimize_user_ids)->where('users.status', 1)->pluck('users.id')->toArray();
        $saleAndOptimizeIds['sale_user_ids']     = array_values(array_filter($sale_user_ids));
        $saleAndOptimizeIds['optimize_user_ids'] = array_values(array_filter($optimize_user_ids));

        $result['saleAndOptimizeUserIds'] = $saleAndOptimizeIds;

        //媒体ID
        $result['media_id'] = !empty($result['media_id']) ? array_map('intval', $result['media_id']) : $result['media_id'];

        $result['media_list'] = [];
        $result['media_names'] = '';
        if(!empty($result['media_id'])){
            $media_type = config('media.media_type');
            foreach ($result['media_id'] as $media_id) {
                $result['media_list'][] = [
                    'label' => $media_type[$media_id] ?? '',
                    'value' => $media_id,
                ];
            }
            $result['media_names'] = implode(',', array_column($result['media_list'], 'label'));
        }

        // 该用户能切换到哪些子系统（数据看板/CRM…），供前端决定是否展示切换入口；
        // 超管走 Role::$superRoles 特判直接给全部子系统，避免超管账号自己没配 role_menus 导致看不到切换入口
        if (in_array($result['role_id'], Role::$superRoles)) {
            $result['sub_systems'] = Menu::query()->where('subsystem_num', '<>', '')->distinct()->pluck('subsystem_num')->values()->toArray();
        } else {
            $roleIds = array_column($result['role'], 'id');
            $result['sub_systems'] = RoleMenu::whereIn('role_id', $roleIds)
                ->where('subsystem_num', '<>', '')
                ->distinct()
                ->pluck('subsystem_num')
                ->values()
                ->toArray();
        }
        Redis::connection('permission_cache')->hset($cache_key, $user_id, json_encode($result));
        Redis::connection('permission_cache')->expire($cache_key, 86400 * 15);
        return $result;
    }

    protected function findParentDepartment($dep_pid, &$info)
    {
        if ($dep_pid == 1) {
            return $info;
        }

        // 使用 Eloquent 查询当前 dep_pid 对应的部门信息
        $department = Department::where('dep_id', $dep_pid)
            ->where('dep_pid', '>', 1)
            ->first();

        // 如果没有找到部门信息，返回 null
        if (!$department) {
            return $info;
        }

        $info = $department;

        // 如果当前部门的 dep_pid 是 1，返回当前部门的 dep_id
        if ($department->dep_pid == 1) {
            return $department->dep_id;
        }

        // 否则，递归查找上一级部门
        return $this->findParentDepartment($department->dep_pid, $info);
    }


    /**
     * 获取部门组长
     */
    protected function getDepartLeader($dep_id)
    {
        return DepartmentsUsers::query()->leftJoin('users', 'users.userid', 'departments_users.userid')
            ->select(['users.id', 'users.name'])->where(['departments_users.department_id' => $dep_id, 'leader' => 1])
            ->first();
    }

    /**
     * 获取用户数据权限范围
     * @param $data_permission
     * @param $userInfo
     * @return array
     */
    private function getUserPermissions($data_permission, $userInfo = [])
    {
        $user_id = $userInfo['id'] ?? 0;
        // 如果没有配置权限数据，则默认仅查看本人数据
        if (empty($data_permission)) {
            // 查看当前用户绑定角色上的数据权限，如果是多个角色则设置为看个人
            $role_id = RoleUser::query()->where('user_id', $user_id)->pluck('role_id')->toArray();

            // 获取权限的默认结构
            $permissions = ['type' => 'self', 'department_ids' => [], 'permission_ids' => [(int)$user_id], 'subordinate_ids' => [], 'assign_ids' => []];

            // 如果用户绑定了多个角色，则默认权限为查看个人数据
            if (count($role_id) == 1) {
                // 查询角色上的数据权限
                $role_permission = Role::query()->where('id', $role_id[0])->value('data_permission');

                if ($role_permission && !empty($role_permission['type'])) {
                    // 如果角色有数据权限，则更新用户数据权限
                    User::query()->where('id', $user_id)->update(['data_permission' => json_encode($role_permission)]);
                    $data_permission = $role_permission;
                    return $role_permission;  // 返回角色数据权限
                }
            }

            // 如果没有角色权限或多个角色的情况，更新为个人权限
            User::query()->where('id', $user_id)->update(['data_permission' => json_encode($permissions)]);

            return $permissions; // 返回个人权限
        }

        // 初始化返回数据
        $data = [
            'type'            => $data_permission['type'],
            'department_ids'  => [],
            'permission_ids'  => $data_permission['permission_ids'] ?? [],
            'subordinate_ids' => [],
            'assign_ids'      => $data_permission['permission_ids'] ?? []
        ];

        $permissionType = $data_permission['type'];

        switch ($permissionType) {
            case User::PERMISSION_TYPE_SELF:// 只查看本人数据
                // 只查看本人数据，合并传入的 permission_ids（如果有）和当前用户ID
                $data['permission_ids'] = array_merge([$user_id], $data['permission_ids']);
                break;
            case User::PERMISSION_TYPE_ALL:// 查看所有数据
                // 查看所有数据：权限列表为空（表示无需限制）
                $data['permission_ids'] = [];
                break;
            case User::PERMISSION_TYPE_SPECIFIC_DEPARTMENTS:// 获取指定部门(包含下级部门)
                // 指定部门：直接使用传入的部门IDs
                $data['department_ids'] = $data_permission['department_ids'];
                // 根据部门IDs查询出部门表中对应的dep_id
                $dep_ids = Department::query()->whereIn('id', $data_permission['department_ids'])->pluck('dep_id')->toArray();

                // 获取当前部门下的所有子部门的dep_id（包含下级）
                $subdep_ids = $this->departmentRepository->getAllChildDepartmentIds($dep_ids);

                // 通过中间表获取这些dep_ids下的用户ID列表
                $user_ids = $this->departmentsUsersRepository->getUserIdsByDepart($subdep_ids);
                // 合并用户ID（指定部门下的、传入的 permission_ids 以及当前用户ID）
                $data['permission_ids'] = array_merge($user_ids, $data_permission['permission_ids'] ?? [], [$user_id]);
                // 子级（下属）权限同样设置为指定部门下的用户ID
                $data['subordinate_ids'] = $user_ids;

                // 获取部门的自增ID
                $data['department_ids'] = Department::query()->whereIn('dep_id', $subdep_ids)->pluck('id')->toArray();

                break;
            case User::PERMISSION_TYPE_DEPARTMENT_AND_SUB:// 本部门及下级部门
                // 本部门及下级部门：先加载当前用户所属部门数据
                $userdep_ids = array_column($userInfo['departments'] ?? [], 'dep_id');
                // 获取当前部门下的所有子部门的dep_id（包含下级）
                $allDep_ids = $this->departmentRepository->getAllChildDepartmentIds($userdep_ids);
                // 获取这些部门下的所有用户ID
                $user_ids                = $this->departmentsUsersRepository->getUserIdsByDepart($allDep_ids);
                $data['subordinate_ids'] = array_values(array_filter($user_ids));
                // 权限ID 合并为部门下的用户ID，加上传入的 permission_ids 和当前用户ID
                $data['permission_ids'] = array_merge(array_filter($user_ids), $data_permission['permission_ids'] ?? [], [$user_id]);
                // 查询部门表，根据dep_id更新为部门表的自增ID
                $data['department_ids'] = Department::query()->whereIn('dep_id', $allDep_ids)->pluck('id')->toArray();
                break;
            default:
                // 其他情况保持默认值（可根据业务扩展）
                break;
        }

        // 确保所有ID数组的值均为整数
        foreach (['permission_ids', 'department_ids', 'subordinate_ids', 'assign_ids'] as $key) {
            if (!empty($data[$key])) {
                $data[$key] = array_map('intval', (array)$data[$key]);
            }
        }

        return $data;
    }

    /**
     * Boot up the repository, pushing criteria
     */
    public function boot()
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }

    /**
     * 从jwt获取token
     * @param $user
     * @return bool|false|string|array
     * @throws RepositoryException
     */
    public function token($user)
    {
        try {
            if (!$token = JWTAuth::attempt($user)) {
                return false;//账户与密码不一致
            } else {
                // 使用JWTAuth::setToken() 设置当前的Token
                JWTAuth::setToken($token);
                // 获取当前认证用户
                //获取tokenid
                $payload = JWTAuth::getPayload($token)->toArray();
                $tokenId = $payload['jti'] ?? null; // 从数组中获取jti
                // 获取当前用户的ID
                $userId        = JWTAuth::user()->getAuthIdentifier();
                $jti_token_key = $this->jtiKeyPrefix . ":{$userId}:" . $tokenId;
                Redis::connection('permission_cache')->setex($jti_token_key, config("permissions.ttl"), $token);
                return ["tokenId" => $tokenId, "token" => $token];//返回token值
            }
        } catch (JWTException $e) {
            // 捕获所有其他异常
            return response()->json(['error' => 'could_not_create_token', 'message' => $e->getMessage()], 500);
        }
    }


    /**
     * 从jwt获取token
     * @param $user
     * @return bool|false|string|array
     * @throws RepositoryException
     */
    public function openidToken($user)
    {
        try {
            if (!$token = JWTAuth::fromUser($user)) {
                return false;//账户与密码不一致
            } else {
                // 使用JWTAuth::setToken() 设置当前的Token
                JWTAuth::setToken($token);
                // 获取当前认证用户
                //获取tokenid
                $payload = JWTAuth::getPayload()->toArray();

                $tokenId = $payload['jti'] ?? null; // 从数组中获取jti
                $ttl = Carbon::now()->endOfDay()->diffInSeconds(Carbon::now());
                // 获取当前用户的ID
                $jti_token_key = $this->jtiKeyPrefix . ":{$user->id}:" . $tokenId;
                Redis::connection('permission_cache')->setex($jti_token_key, $ttl, $token);
                return ["tokenId" => $tokenId, "token" => $token];//返回token值
            }
        } catch (JWTException $e) {
            // 捕获所有其他异常
            return response()->json(['error' => 'could_not_create_token', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 用户列表
     * @param $data
     * @return mixed
     */
    public function handleUserList($data)
    {
        $limit = $data['limit'] ?? 10;

        // 根据角色唯一标识
        $currentUser = auth()->user();
        $isSuperAdmin = false;
        if ($currentUser) {
            $roles = collect($currentUser->role->toarray());
            $isSuperAdmin = $roles->contains(function ($role) {
                return isset($role['index']) && $role['index'] == User::SUPER_DAMIN;
            });
        }

        // 获取表前缀
        $prefix = DB::connection('auth_mysql')->getTablePrefix();

        $query = $this->getModel()->select(
            [
                'users.name', 'users.corp_id', 'users.source', 'users.id', 'users.email', 'users.head_img',
                'users.created_at', 'users.status', 'users.mobile', 'users.area', 'users.userid','users.media_id',
                'users.data_permission','users.openid',
                // 使用带前缀的表别名
                DB::raw('GROUP_CONCAT(' . $prefix . 'du.department_id) as department_ids'),
                DB::raw('GROUP_CONCAT(' . $prefix . 'd.name) as department_name'),
            ])
            ->leftJoin('departments_users as du', function ($join) {
                $join->on('du.userid', '=', 'users.userid')->whereNull('du.deleted_at');
            })
            ->leftJoin('departments as d', 'd.dep_id', '=', 'du.department_id')
            ->where('users.email', '!=', '');

        // 基础查询条件
        if (!empty($data['department_id'])) {
            $query = $query->where('du.department_id', $data['department_id']);
        }
        if (!empty($data['role_id'])) {
            $query = $query->where('users.role_id', $data['role_id']);
        }
        if (isset($data['status']) && $data['status'] != "") {
            $query = $query->where('users.status', $data['status']);
        }
        if (!empty($data['name'])) {
            $query->where(fn($q) => $q->where('users.name', 'like', "%{$data['name']}%")
                ->orWhere('users.email', 'like', "%{$data['name']}%")
            );
        }


        // 部门权限限制 - 只有普通用户且没有 all_corp_super_type 参数时才添加部门限制
        if (!$isSuperAdmin && $currentUser && empty($data['all_corp_super_type'])) {
            $userData = $currentUser->toArray();
            $dep_ids = array_column($userData['departments'], 'dep_id');
            $allDep_ids = $this->departmentRepository->getAllChildDepartmentIds($dep_ids);
            if (!empty($allDep_ids)) {
                $query = $query->whereIn('du.department_id', $allDep_ids);
            } else {
                // 如果普通用户没有任何部门，看不到任何用户
                $query = $query->whereRaw('1=0');
            }
        }

        // 数据看板根据用户ID查询
        if (!empty($data['team_id'])) {
            $departmentIds = Department::query()->whereIn('id', $data['team_id'])->pluck('dep_id')->toArray();
            $subdep_ids = $this->departmentRepository->getAllChildDepartmentIds($departmentIds);
            $query = $query->whereIn('du.department_id', $subdep_ids);
        }

        $query = $query->groupBy('users.id');
        $result = $query->orderBy('id', 'DESC')->paginate($limit);

        $result->getCollection()->each(function ($user) {
            $user->setAppends([]); // 禁用 append
        });
        $result = $result->toArray();

        if (!empty($result['data'])) {
            $area_name = User::$area;
            $status = User::$status;

            foreach ($result['data'] as &$item) {
                $item['area_name'] = !empty($item['area']) ? $area_name[$item['area']] : null;
                $item['status_name'] = $status[$item['status']] ?? '';
            }
        }

        if (!empty($result['data'])) {
            // 预加载所有用户的关联数据
            $userIds = collect($result['data'])->pluck('id')->toArray();

            //媒体信息
            $mediaInfo = config('media.media_type');

            // 批量查询区域信息
            $areaInfo         = User::$area;
            $areaUsers        = AreaUser::whereIn('user_id', $userIds)->get();
            $areaUsersGrouped = $areaUsers->groupBy('user_id');

            // 批量查询角色信息
            $roleUsers        = RoleUser::whereIn('user_id', $userIds)->get();
            $roleIds          = $roleUsers->pluck('role_id')->unique()->toArray();
            $roles            = Role::whereIn('id', $roleIds)->pluck('name', 'id');
            $roleUsersGrouped = $roleUsers->groupBy('user_id');

            // 批量查询部门信息
            $departmentUsers = Department::leftJoin('departments_users as du', 'departments.dep_id', '=', 'du.department_id')
                ->whereIn('du.userid', collect($result['data'])->pluck('userid')->toArray())
                ->select(['departments.name', 'du.userid', 'du.leader'])
                ->get()
                ->groupBy('userid');

            // 处理每个用户的数据
            foreach ($result['data'] as &$item) {
                $userId = $item['id'];

                $permission_type = $item['data_permission']['type'] ?? null;
                switch ($permission_type) {
                    case 'all':
                        $item['permission_type_label'] = '全部';
                        break;
                    case 'self':
                        $item['permission_type_label'] = '仅本人';
                        break;
                    case 'specificDepartments':
                        $department_ids = $item['data_permission']['department_ids'] ?? [];
                        $departmentNames = empty($department_ids) ? '' : Department::query()->whereIn('id', $department_ids)->pluck('name')->implode(', ');
                        $item['permission_type_label'] = $departmentNames;
                        break;
                    case 'departmentAndSub':
                        $item['permission_type_label'] = '本部门及下级';
                        break;
                    default:
                        $item['permission_type_label'] = '';
                        break;
                }

                // 处理媒体名称
                $item['media_name'] = collect($item['media_id'])->map(function ($mediaId) use ($mediaInfo) {
                    return $mediaInfo[$mediaId] ?? '';
                })->implode('、');

                // 处理区域名称
                $areaIds           = $areaUsersGrouped->get($userId, collect())->pluck('area_id')->unique();
                $item['area_name'] = $areaIds->map(function ($areaId) use ($areaInfo) {
                    return $areaInfo[$areaId] ?? '';
                })->implode('、');

                // 处理角色名称
                $roleIds           = $roleUsersGrouped->get($userId, collect())->pluck('role_id')->unique();
                $item['role_name'] = $roleIds->map(function ($roleId) use ($roles) {
                    return $roles[$roleId] ?? '';
                })->implode('、');

                // 处理部门名称
                $departmentNames         = $departmentUsers->get($item['userid'], collect())->map(function ($department) {
                    return $department->name . ($department->leader == 1 ? '（主管）' : '');
                })->implode('、');
                $item['department_name'] = $departmentNames;


                // 数据权限
                if (!is_null($item['data_permission'])) {
                    if (empty($item['data_permission']['department_ids'])) {
                        $item['data_permission']['department_ids'] = [];

                    } else {
                        $item['data_permission']['department_ids'] = Department::query()->select(['id', 'name'])->whereIn('id', $item['data_permission']['department_ids'])->get();
                    }
                    if (empty($item['data_permission']['permission_ids'])) {
                        $item['data_permission']['permission_ids'] = [];
                    } else {
                        $item['data_permission']['permission_ids'] = User::query()->select(['id', 'name', 'area'])->whereIn('id', $item['data_permission']['permission_ids'])->get();
                    }
                }
            }
        }

        return $result;

    }

    /**
     * 添加编辑用户
     * @param $data
     * @return mixed
     * @throws RepositoryException
     *
     */
    public function handleUser($data)
    {
        $data['system_id']='RD0001';
        return DB::transaction(function () use ($data) {
            $id  = $data['id'] ?? 0;

            $exits = $this->getModel()->where('email', $data['email'])->when(!empty($id), function ($query) use ($id) {
                $query->where('id', '!=', $id);
            })->exists();
            if($exits){
                throw new RepositoryException("邮箱已存在");
            }

            $now = now()->format('Y-m-d H:i:s');

            $nameExists = $this->getModel()->where('name', $data['name'])
                ->when(!empty($id), function ($query) use ($id) {
                    $query->where('id', '!=', $id);
                })->exists();
            if ($nameExists) {
                throw new RepositoryException("用户名已存在");
            }

            $attributes = [
                'name'     => $data['name'],
                'email'    => $data['email'],
                'status'   => $data['status'],
                'mobile'   => $data['mobile'],
            ];
            if(!empty($data['password'])){
                $attributes['password'] = $this->generatePassword($data['password']);
            }

            $departmentIds = explode(',', $data['department_id']);

            if ($id) {
                $user = $this->isExit($id);

                $this->getModel()->where('id', $id)->update(array_merge($attributes, ['updated_at' => $now]));

                DepartmentsUsers::where('userid', $user->userid)->forceDelete();//真实删除

                $deptRows = [];
                foreach ($departmentIds as $deptId) {
                    $deptRows[] = [
                        'userid'        => $user->userid,
                        'name'          => $data['name'],
                        'email'         => $data['email'],
                        'department_id' => $deptId,
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ];
                }
                DepartmentsUsers::insert($deptRows);
                //记录user_roles表
                if(!empty($data['role_id'])){
                    $data['id'] = $user->id;
                    $data['user_id'] = $user->userid;
                    $data['sale_info'] = [];
                    if(!empty($data['data_permission']['permission_ids'])){
                        $data['sale_info'] = $this->getModel()->select(['id','name'])->whereIn('id', $data['data_permission']['permission_ids'])->get()->toArray();
                    }
                    $this->departmentsUsersRepository->updateAuthorize($data);
                }
                return $user;
            }

            $uniqueId = str_replace('-', '', Str::uuid());
            $userData = array_merge($attributes, [
                'userid'     => $uniqueId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $user = $this->getModel()->insert($userData);

            $deptRows = [];
            foreach ($departmentIds as $deptId) {
                $deptRows[] = [
                    'userid'        => $uniqueId,
                    'name'          => $data['name'],
                    'email'         => $data['email'],
                    'department_id' => $deptId,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            }
            DepartmentsUsers::insert($deptRows);
            $userInfo = $this->getModel()->where('userid', $uniqueId)->first();
            //记录user_roles表
            if(!empty($data['role_id'])){
                $data['sale_info'] = [];
                if(!empty($data['data_permission']['permission_ids'])){
                    $data['sale_info'] = $this->getModel()->select(['id','name'])->whereIn('id', $data['data_permission']['permission_ids'])->get()->toArray();
                }
                $this->departmentsUsersRepository->setUserAuthorize($data, $userInfo['id'], $uniqueId);
            }

            return $userInfo;
        });
    }


    /**
     * 判断信息是否存在
     * @param $id
     * @return mixed
     * @throws RepositoryException
     */
    public function isExit($id)
    {
        $info = $this->where('id', $id)->first();
        if (!$info) {
            throw new RepositoryException('信息不存在', 10002);
        }
        return $info;
    }

    /**
     * 根据邮箱匹配处理人员数据 - 钉钉事件调用
     * @param $dataToInsertOrUpdate
     * @param $emails
     * @param $depId
     * @return true
     */
    public function updateUsers($dataToInsertOrUpdate, $emails, $depId)
    {
        //获取部门信息
        $departmentOne    = $this->departmentRepository->getModel()->select(['dep_id', 'id'])->where("dep_id", $depId)->get()->first();
        $user_defalut_pwd = config('admin.user_defalut_pwd');
        $existingUsers    = $this->whereIn('email', $emails)->get()->keyBy('email');
        $area_info        = array_flip(User::$area);
        DB::transaction(function () use ($dataToInsertOrUpdate, $existingUsers, $user_defalut_pwd, $departmentOne, $area_info) {
            foreach ($dataToInsertOrUpdate as $data) {
                $email = $data['email'];
                if (empty($email)) {
                    continue;
                }
                $userData["userid"]        = $data["userid"];
                $userData["email"]         = $data["email"];
                $userData["department_id"] = $departmentOne->id ?? 0;
                $userData['mobile']        = $data['mobile'] ?? "";
                $userData['area']          = $area_info[$data['area']] ?? "";
                $userData['unionid']       = $data['unionid'] ?? '';
                $userData["name"]          = $data["name"];
                $userData['title']         = $data['title'] ?? '';
                if ($existingUsers->has($email)) {
                    // 更新现有记录
                    $existingUsers[$email]->update($userData);
                } else {
                    $userData["head_img"] = $data["head_img"];
                    $userData["password"] = $this->generatePassword($user_defalut_pwd);
                    // 插入新记录
                    User::query()->insert($userData);
                }
            }
        });
        return true;
    }


    /**
     * 更新密码
     */
    public function setPassword($data)
    {
        //个人修改判断是否是自己的数据
        if (!in_array(auth()->user()->role_id, Role::$superRoles) && isset($data['email']) && auth()->user()->email != $data['email'])
            throw new RepositoryException('对不起，您没有该操作权限', 10080);

        //判断数据是否存在
        $info = $this->isExit($data['id']);

        //密码加密同时存入本地库
        $data['password'] = $this->generatePassword($data['password']);
        if ($this->where(['id' => $data['id']])->update($data)) {
            $jti_token_key = $this->jtiKeyPrefix . ":{$data['id']}:*";
            [$cursor, $keys] = Redis::connection('permission_cache')->scan(0, [
                'match' => $jti_token_key,
                'count' => 100  // 每次扫描的数量
            ]);
            Redis::connection('permission_cache')->del($keys);
            return $info;
        } else
            return false;
    }

    /**
     * 发送邮件
     */
    public function sendMail($email)
    {
        //判断数据是否存在
        if (!$res = User::where(['email' => $email])->first())
            throw new RepositoryException('该邮箱没有被注册', 20114);

        //判断60秒可重复发送
        $userCode = Redis::connection('permission_cache')->get('user' . $res->id);
        if ($userCode) {
            $userCode = unserialize($userCode);
            if (time() - $userCode['time'] < 60)
                throw new RepositoryException('操作太频繁', 20134);
        }

        //随机code
        $data = [
            'code' => mt_rand(1000, 9999),
            'time' => time()
        ];
        //写入redis
        Redis::connection('permission_cache')->setex('user' . $res->id, 300, serialize($data));
        Mail::send('email.sendmail', ['code' => $data['code']], function ($message) use ($email) {
            $message->to($email, '用户中心-重置密码')->subject('用户中心-重置密码');
        });
        return true;
    }

    /**
     * 重设密码
     */
    public function resetPassword($data)
    {
        //判断数据是否存在
        if (!$res = User::where('email', $data['email'])->where('status', '!=', 0)->first())
            throw new RepositoryException('该邮箱没有被注册', 20114);

        //判断60秒可重复发送
        $userCode = Redis::connection('permission_cache')->get('user' . $res->id);
        if (!$userCode)
            throw new RepositoryException('验证码失效，请重新发送', 21135);

        $userCode = unserialize($userCode);
        if ($data['code'] != $userCode['code'])
            throw new RepositoryException('验证码错误', 21136);
        //删除掉验证码
        Redis::connection('permission_cache')->del('user' . $res->id);
        $res->password = $this->generatePassword($data['password']);
        if ($res->save())
            return true;
        else
            throw new RepositoryException('密码修改失败', 21137);
    }

    /**
     * 获取配置
     * @param $data
     * @return array
     */
    public function getConfigs($data): array
    {
        $result = [];
        $user = auth()->user();

        //数据权限类型
        $data_permission_type           = User::$permissions_type;
        $result['data_permission_type'] = collect($data_permission_type)
            ->filter(function ($item, $key) use ($user) {
                // 如果当前用户不是超级管理员，并且权限类型为 'all' 或 'specificDepartments'，则过滤掉
                return $user->role_id == User::$superman ? true : !in_array($key, ['all', 'specificDepartments']);
            })
            ->map(function ($item, $key) {
                return [
                    'value' => $key,
                    'label' => $item,
                ];
            })
            ->values() // 重置数组索引
            ->toArray();
        if (!empty($data['is_data_permissions'])) {
            return $result;
        }

        //区域
//        dd($user->toArray());
        $result['area'] = collect(User::$area)
            ->filter(function ($item, $key) use ($user) {
                // 如果用户角色不是超级管理员 (role_id 1) 且该地区在用户分配的区域内，则保留
                $area_ids = AreaUser::query()->where('user_id', $user->id)->pluck('area_id')->toArray();
                return $user->role_id == User::$superman ? true : in_array($key, $area_ids);
            })->map(function ($item, $key) {
                return [
                    'value' => $key,
                    'label' => $item,
                ];
            })->values()->toArray();

        //媒体
        $media = config('media.media_type');
        $result['media'] = collect($media)
            ->filter(function ($item, $key) use ($user) {
                // 如果用户角色不是超级管理员 (role_id 1) 且该地区在用户分配的区域内，则保留
                return $user->role_id == User::$superman ? true : in_array($key, $user->media_id ?? []);
            })->map(function ($item, $key) {
                return [
                    'value' => $key,
                    'label' => $item,
                ];
            })->values()->toArray();

        //角色类型
        $role_type = config('media.role_type');
        collect($role_type)->map(function ($item, $key) use (&$result) {
            $result['role_type'][] = [
                'value' => $key,
                'label' => $item,
            ];
        });

        //部门类型
        $department_type = config('media.department_type');
        collect($department_type)->map(function ($item, $key) use (&$result) {
            $result['department_type'][] = [
                'value' => $key,
                'label' => $item,
            ];
        });

        // 部门标签类型
        $depart_tag_type = config('media.department_tag_type');
        collect($depart_tag_type)->map(function ($item, $key) use (&$result) {
            $result['depart_tag_type'][] = [
                'value' => $key,
                'label' => $item,
            ];
        });

        return $result;
    }

    /**
     * 按照角色查找对应人员
     * @param $role_id
     * @param $user_name
     * @return mixed
     */
    public function getRoleUserList($role_id=[], $user_name = '', $department_ids = [])
    {
        $model = $this->getModel()->select(['users.id', 'users.area', 'users.name', 'departments_users.department_id as team_id']);
        if (!empty($role_id)) {
            $model = $model->whereIn('role_users.role_id', $role_id);
        }
        if(!empty($user_name)){
            $model = $model->where('users.name', 'like', '%'.$user_name.'%');
        }
        if(!empty($department_ids)){
            $model = $model->whereIn('departments_users.department_id', $department_ids);
        }
        $role_user = app(RoleUser::class)->getTable();
        $depart_user = app(DepartmentsUsers::class)->getTable();
        return $model->leftJoin("{$role_user}", 'role_users.user_id', 'users.id')->where('users.status', 1)
            ->leftJoin("{$depart_user}", 'users.userid', 'departments_users.userid')
            ->whereNull('departments_users.deleted_at')->whereNull('users.deleted_at')
            ->without('role', 'departments')
            ->groupBy('users.userid')
            ->orderBy('users.area', 'asc')
            ->get();
    }


    /**
     * 按照部门查找对应人员
     * @param $data
     * @return mixed
     */
    public function getDepartmentUserList($data=[])
    {
        $limit = !empty($data['per_page']) ? $data['per_page'] : $data['limit'];
        $model = $this->getModel()->select(['users.id', 'users.area', 'users.name', 'departments_users.department_id as team_id']);
        if(!empty($data['user_name'])){
            $model = $model->where('users.name', 'like', '%'.$data['user_name'].'%');
        }
        if(!empty($data['department_ids'])){
            $model = $model->whereIn('departments_users.department_id', $data['department_ids']);
        }
        $depart_user = app(DepartmentsUsers::class)->getTable();
        return $model
            ->leftJoin("{$depart_user}", 'users.userid', 'departments_users.userid')
            ->whereNull('departments_users.deleted_at')->whereNull('users.deleted_at')
            ->without('role','departments')
            ->groupBy('users.userid')
            ->orderBy('users.area', 'asc')
            ->paginate($limit)->toArray();
    }
}
