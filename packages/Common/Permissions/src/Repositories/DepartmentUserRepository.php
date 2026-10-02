<?php

namespace Common\Permissions\Repositories;


use Common\Exceptions\RepositoryException;
use Common\Libs\Common;
use Common\Permissions\Models\AreaUser;
use Common\Permissions\Models\Department;
use Common\Permissions\Models\DepartmentsUsers;
use Common\Permissions\Models\Role;
use Common\Permissions\Models\RoleUser;
use Common\Permissions\Models\SaleUser;
use Common\Permissions\Models\User;
use Prettus\Repository\Eloquent\BaseRepository;
use DB;
use Log;

class DepartmentUserRepository extends BaseRepository
{
    public function presenter()
    {
        return null;
    }

    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return DepartmentsUsers::class;
    }

    /**
     * 获取部门+人员
     * @return array
     */
    public function getDepartmentAndUsers($need_staff = false, $flag = true): array
    {
        return $this->getDepartmentTree($need_staff, $flag);
    }

    public function getDepartmentTree($need_staff = false, $flag = true, $parentId = 1): array
    {
        // 先获取所有部门数据及其关联用户
        $departments = Department::select(['id', 'name', 'dep_id', 'dep_pid'])
            ->get()
            ->keyBy('dep_id'); // 将部门按 dep_id 索引，方便后续查找
        // 获取所有部门下的用户
        $departmentUsers = $this->getModel()->select('departments_users.userid', 'departments_users.name', 'departments_users.email', 'departments_users.department_id',
            DB::raw('CONCAT(uc_departments_users.email, "_", uc_departments_users.userid, "_", uc_departments_users.department_id) AS unique_key'),
            'users.id', 'users.data_permission', 'users.media_id')
            ->leftJoin('users', 'departments_users.userid', '=', 'users.userid')
//            ->where('users.email', '!=', '')
//            ->where('departments_users.email', '!=', '')
            ->whereNull('users.deleted_at')
            ->get()
            ->groupBy('department_id');
        // 使用递归构建部门树
        return $this->buildDepartmentTree($departments, $departmentUsers, $parentId, $need_staff, $flag);
    }

    protected function buildDepartmentTree($departments, $departmentUsers, $parentId, $need_staff, $flag): array
    {
        $result = [];

        // 查找所有子部门
        $subDepartments = $departments->filter(function ($department) use ($parentId) {
            return $department->dep_pid == $parentId;
        });

        // 构建每个部门及其子部门
        foreach ($subDepartments as $department) {
            $departmentData = [
                'id'         => $department->id,
                'name'       => $department->name,
                'dep_id'     => $department->dep_id,
                'dep_pid'    => $department->dep_pid,
//                'user_num'   => 0,
                'user_num'   => $this->getSubDepartmentUsersData($departments, $departmentUsers, $department->dep_id),
//                'children'   => $need_staff == true ? array_merge($this->buildDepartmentTree($departments, $departmentUsers, $department->dep_id, $need_staff, $flag), $departmentUsers->get($department->dep_id, collect())->toArray()) : $this->buildDepartmentTree($departments, $departmentUsers, $department->dep_id, $need_staff, $flag),
                'disabled'   => (boolean)$flag,
                'unique_key' => uniqid()
            ];
            $result[]       = $departmentData;
        }

        return $result;
    }

    /**
     * 获取指定部门及其子部门的用户数据
     *
     * @param Collection $departments 部门集合
     * @param Collection $departmentUsers 部门用户集合
     * @param int $departmentId 当前部门 ID
     * @param bool $returnUserIds 是否返回用户 ID（默认 false，返回用户总数）
     * @return int|array 用户总数或用户 ID 列表
     */
    private function getSubDepartmentUsersData($departments, $departmentUsers, int $departmentId, bool $returnUserIds = false)
    {

        // 初始化结果
        $result = $returnUserIds
            ? collect($departmentUsers->get($departmentId, collect()))->toArray()
            : $departmentUsers->get($departmentId, collect())->count();

        // 获取子部门
        $subDepartments = $departments->filter(fn($department) => $department->dep_pid == $departmentId);
//        foreach ($subDepartments as $subDepartment) {
//            $subResult = $this->getSubDepartmentUsersData($departments, $departmentUsers, $subDepartment->dep_id, $returnUserIds);
//
//            // 合并或累加结果
//            if ($returnUserIds) {
//                $result = array_merge($result, $subResult);
//            } else {
//                $result += $subResult;
//            }
//        }

        return $result;
    }

    /**
     * 根据用户获取其领导的部门下所有用户 ID
     *
     * @param $userId 用户 ID
     * @return array 所有用户 ID
     */
    public function getLeaderDepartmentUsers($userId = null): array
    {
        $userId = is_null($userId) ? auth()->user()->userid : $userId;
        if (auth()->user()->id == 1 || in_array(auth()->user()->role_id, Role::$superRoles)) {
            return [];
        }

        // 获取该用户领导的部门 ID
        $leaderDepartments = DepartmentsUsers::where('userid', $userId)
            ->where('leader', 1)
            ->pluck('department_id')
            ->toArray();

        if (empty($leaderDepartments)) {
            return [auth()->user()->id]; // 当前用户不是任何部门的领导
        }

        // 获取所有部门数据
        $departments = Department::select(['id', 'name', 'dep_id', 'dep_pid'])
            ->get()
            ->keyBy('dep_id');

        // 获取所有部门用户
        $departmentUsers = DepartmentsUsers::select('departments_users.department_id', 'users.id as user_id')
            ->join('users', 'departments_users.userid', '=', 'users.userid') // 通过 userid 关联
            ->get()
            ->groupBy('department_id');

        // 提取用户主键 ID
        $departmentUsers = $departmentUsers->map(fn($users) => $users->pluck('user_id')->toArray());


        // 收集用户 ID
        $allUserIds = [];
        foreach ($leaderDepartments as $departmentId) {
            $allUserIds = array_merge($allUserIds, $this->getSubDepartmentUsersData($departments, $departmentUsers, $departmentId, true));
        }

        return array_unique(array_filter($allUserIds, fn($value) => $value !== null));
    }


    /**
     * 根据条件过滤用户信息
     * @param $where
     * @param $field
     * @return mixed
     */
    public function getDepartmentUserList($where, $field = ['*'])
    {
        $query = $this->select($field);
        if ($where["userid"]) {
            $query->whereIn('userid', $where["userid"]);
            unset($where["userid"]);
        }

        $query->where($where);
        return $query->get()->toArray();
    }

    /**
     * 获取用户的上级信息
     * @param $userIds
     * @return mixed
     */
    public function getLeadInfoById(string $userId)
    {

        $departmentId = $this->getModel()->where('userid', $userId)->pluck('department_id');
        if($departmentId->isEmpty()){
            return [];
        }
        $leaders      = $this->getModel()->where('leader', 1)
            ->whereIn('department_id', $departmentId)
            ->get();
        if ($leaders->isEmpty()) {
            // 获取上级部门 ID
            $depPid = Department::where('dep_id', $departmentId)->value('dep_pid');

            if ($depPid) {
                // 查询上级部门的领导
                $leaders = $this->getModel()
                    ->where('leader', 1)
                    ->where('department_id', $depPid)
                    ->get();
            }
        }

        return $leaders->first()->toArray();
    }

    /**
     * 根据部门ID查询员工ID
     * @param array $dep_ids
     * @return mixed
     */
    public function getUserIdsByDepart(array $dep_ids)
    {
        return $this->getModel()->leftJoin('users', 'departments_users.userid', 'users.userid')
            ->whereIn('departments_users.department_id', $dep_ids)
            ->pluck('users.id')->toArray();
    }


    /**
     * 新增用户授权
     * @param $data
     * @return true
     * @throws RepositoryException
     */
    public function createAuthorize($data): bool
    {
        //查询用户是否已经授权
        $check = User::query()->where(['id' => $data['id'], 'is_authorize' => User::IS_AUTHORIZED])->first();
        if ($check) {
            throw new RepositoryException('该用户已授权', 12001);
        }

        \Illuminate\Support\Facades\DB::beginTransaction();

        $user_id      = $data['id'];
        $ding_user_id = $data['user_id'];
        try {

            //记录user_roles表
            $this->setUserAuthorize($data, $user_id, $ding_user_id);

            //更新用户的状态为已授权
            User::query()->where(['id' => $data['id']])->update(['is_authorize' => User::IS_AUTHORIZED]);

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw new RepositoryException($e->getMessage() . $e->getFile(), $e->getCode());
        }
    }

    /**
     * 更新授权
     * @param $data
     * @return bool
     * @throws RepositoryException
     */
    public function updateAuthorize($data): bool
    {
        DB::beginTransaction();

        $user_id      = $data['id'];
        $ding_user_id = $data['user_id'];
        try {
            //删除记录
            $corp_id = auth()->user()->corp_id;
            RoleUser::query()->where('user_id', $user_id)->delete();
            AreaUser::query()->where(['user_id' => $user_id, 'corp_id' => $corp_id])->delete();
            $role_ids = explode(',', $data['role_id']);
            if (in_array(Role::$saleAssistantRoleID, $role_ids) && !empty($data['sale_info'])) {
                SaleUser::query()->where('user_id', $user_id)->delete();
            }

            //记录user_roles表
            $this->setUserAuthorize($data, $user_id, $ding_user_id);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new RepositoryException($e->getMessage() . $e->getFile(), $e->getCode());
        }
        return true;
    }

    /**
     * 设置角色关联数据
     * @param $data
     * @param $user_id
     * @param $ding_user_id
     * @return void
     * @throws Exception
     */
    public function setUserAuthorize($data, $user_id, $ding_user_id)
    {
        $role_ids = explode(',', $data['role_id']);
        // 如果选择的角色包含销售助理角色，必须包含销售人员
        if (in_array(Role::$saleAssistantRoleID, $role_ids) && empty($data['sale_info'])) {
            throw new \Exception('请选择销售人员');
        }

        //更新用户数据权限
        if (!empty($data['data_permission'])) {
            User::query()->where('id', $user_id)->update(['data_permission' => json_encode($data['data_permission'])]);
        }

        if (!empty($data['media_id'])) {
            //更新用户绑定的媒体
            User::query()->where('id', $user_id)->update(['media_id' => explode(',', $data['media_id'])]);
        }
        if (!empty($data['sale_info'])) {
            //更新选中信息-前端反选用
            User::query()->where('id', $user_id)->update(['sale_info' => json_encode($data['sale_info'])]);

            // 插入销售人员关联数据
            $sale_ids = array_column($data['sale_info'], 'id');
            $this->processUserAssociations(
                implode(',', $sale_ids),
                SaleUser::class,
                ['user_id' => $user_id, 'ding_user_id' => $ding_user_id],
                'sale_id'
            );
        }

        // 插入角色关联数据
        $this->processUserAssociations(
            $data['role_id'] ?? '',
            RoleUser::class,
            ['user_id' => $user_id, 'ding_user_id' => $ding_user_id],
            'role_id'
        );

    }

    /**
     * 处理用户关联表插入数据
     *
     * @param string $ids 逗号分隔的ID字符串
     * @param string $modelClass 目标模型类
     * @param array $commonFields 公共字段数组
     * @param string $targetField 目标字段名称
     * @return void
     */
    private function processUserAssociations(string $ids, string $modelClass, array $commonFields, string $targetField)
    {
        // 如果ID为空，直接返回
        if (empty($ids)) {
            return;
        }

        $data = array_map(function ($id) use ($commonFields, $targetField) {
            return array_merge($commonFields, [$targetField => $id]);
        }, explode(',', $ids));

        $this->batchInsertRecords($data, $modelClass);
    }


    /**
     * 批量插入
     * @param $insertData
     * @param $modelClass
     * @return void
     */
    private function batchInsertRecords($insertData, $modelClass)
    {
        if (!empty($insertData)) {
            $modelClass::query()->insert($insertData);
        }
    }

    public function getUserByName($data)
    {
        $query = $this->select([
            'departments_users.name',
            'departments_users.department_id',
            'departments_users.leader',
            'departments_users.userid',
            'departments.name as department_name'
        ])
            ->join('departments', 'departments_users.department_id', '=', 'departments.dep_id')
            ->when(!empty($data['name']), function ($q) use ($data) {
                return $q->where('departments_users.name', 'like', '%' . $data['name'] . '%');
            })
            ->where("departments_users.department_id",">",0)
            ->whereNull('departments.deleted_at');
        $result = $query->get();
/*        $query = $this->select(['name', 'department_id', 'leader', 'userid'])->with(['departments']);
        if (!empty($data['name'])) {
            $query->where('name', 'like', '%' . $data['name'] . '%');
        }

        $result = $query->get();*/

        $result = $result ? $result->toArray() : [];
        foreach ($result as &$user_item) {
            $user_item['id']     = User::query()->where('userid', $user_item['userid'])->value('id'); //用户表主键ID
           // $user_item['userid'] = $user_item['userid'];
           // $user_item['info']   = $user_item['name'] . '-' . (!empty($user_item['departments']) ? $user_item['departments']['name'] : '') . ($user_item['leader'] == 1 ? '-主管' : '');
            $user_item['info']   = $user_item['name'] . '-' . (!empty($user_item['department_name']) ? $user_item['department_name'] : '') . ($user_item['leader'] == 1 ? '-主管' : '');
        }
        return $result;
    }
}
