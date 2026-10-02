<?php

namespace Common\Permissions\Repositories;

use Common\Permissions\Common\RepositoryException;
use Common\Permissions\Common\Common;
use Common\Permissions\Models\Department;
use Common\Permissions\Models\DepartmentsUsers;
use Prettus\Repository\Eloquent\BaseRepository;
use DB;
use Exception;
use Log;
use Common\Permissions\Models\User;

class DepartmentRepository extends BaseRepository
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
        return Department::class;
    }


    /**
     * 获取所有部门
     * @param $data
     * @return array
     */
    public function allDepart($data)
    {
        //获取所有部门
        $department = $this->select(['id', 'pid', 'name as label', 'type', 'dep_id as value']);
        if (!empty($data['name'])) {
            $department = $department->where('name', 'like', '%' . $data['name'] . '%');
        }

        if (!empty($data['tags'])) {
            $tags = is_array($data['tags']) ? $data['tags'] : explode(',', $data['tags']);
            $department = $department->whereIn('tag', $tags);
        }

        $user = auth()->user()->toArray();
        if($user['role_id'] != User::$superman && empty($data['is_all'])){
            $dep_ids = array_column($user['departments'], 'dep_id');
            $department = $department->whereIn('dep_id', $dep_ids)->orWhereIn('dep_pid', $dep_ids);
        }
        return $department->get()->toArray();
//        $common     = new \Common\Libs\Common();
//        return $common->getTree($department);
    }

    /**
     * 部门列表
     * @return array
     */
    public function listDepartment($data)
    {
        $limit = $data['limit'] ?? 10;
        // 获取所有部门下的用户
        $departmentUsers = DepartmentsUsers::select('id', 'userid', 'name', 'email', 'department_id')
            ->get()
            ->groupBy('department_id');

        $departments = $this->pluck('name', 'dep_id')->toArray();

        //获取所有部门
        $department = Department::select(['id', 'pid', 'dep_id', 'dep_pid', 'name', 'created_at', 'type', 'level', 'user_num', 'tag', 'source']);

        if (!empty($data['name'])) {
            $department = $department->where('name', 'like', '%' . $data['name'] . '%');
        }

        if(!empty($data['dep_pid'])){
            $department = $department->where('dep_pid', $data['dep_pid']);
        }

        if(!empty($data['dep_id'])){
            $department = $department->where('dep_id', $data['dep_id']);
        }
        $result = $department->orderBy('id', 'DESC')->paginate($limit);
        if (!empty($result)) {
            $depart_type     = config('media.department_type');
            $depart_tag_type = config('media.department_tag_type');
            foreach ($result as &$value) {
                $value['tag_info']             =
                    [
                        'value' => $value->tag,
                        'label' => $depart_tag_type[$value->tag ?? 0] ?? '',
                    ];
                $value['department_type_name'] = $depart_type[$value['type']] ?? '';
                $value['user_num']             = $departmentUsers->get($value->dep_id, collect())->count();
                $value['parent_name']          = $departments[$value->dep_pid] ?? '-';
            }
        }
        return $result;
    }

    /**
     * 添加、编辑部门信息
     * @param $data
     * @return mixed
     */
    public function handleDepartment($data)
    {
        $id = $data['id'] ?? 0;

        if ($id) {
            $update_data = [
                'name'       => $data['name'] ?? '',
                'dep_id'     => $data['dep_id'] ?? 0,
                'dep_pid'    => $data['dep_pid'] ?? 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            return $this->getModel()->where('id', $id)->update($update_data);
        }

        $department = [
            'name'       => $data['name'] ?? '',
            'dep_pid'    => $data['dep_pid'] ?? 0,
            'created_at' => now()->format('Y-m-d H:i:s'),
            'updated_at' => now()->format('Y-m-d H:i:s'),
            'source'     => 1
        ];
        $department['dep_id'] = $this->generateDepId();
        return $this->getModel()->insert($department);
    }

    /**
     * 删除部门信息
     * @param $data
     * @return mixed
     * @throws RepositoryException
     */
    public function deleteDepartment($data)
    {
        $id = $data['id'] ?? 0;
        $row = $this->getModel()->where('id', $id)->first();
        if(!$row){
            throw new RepositoryException('部门信息不存在');
        }
        if($row->source != 1){
            throw new RepositoryException('内部部门不允许删除');
        }

        return $this->getModel()->where('id',$id)->update(['deleted_at' => date('Y-m-d H:i:s')]);
    }

    public function generateDepId()
    {
        $base = 998203065;
        while (true) {
            $base++;

            $exists = $this->getModel()
                ->where('dep_id', $base)->orWhere('dep_pid', $base)
                ->exists();

            if (!$exists) {
                return $base;
            }
        }

    }

    /**
     * 修改部门
     * @param $data
     * @param $type
     * @return mixed
     * @throws RepositoryException
     */
    public function updateDepart($data)
    {
        $row = $this->isExist(['id' => $data['id']]);
        if (!$row) {
            throw new RepositoryException('数据不存在');
        }
        $date    = date('Y-m-d H:i:s');
        $db_data = [
            'updated_at' => $date,
            'tag'        => $data['tag'] ?? 0,
        ];
        $res     = $this->updateField(['id' => $data['id']], $db_data);
        if ($res) {
            return true;
        }
        throw new RepositoryException('修改失败', '20003');
    }


    /**
     * 部门人数
     * @param $id
     * @param $type
     * @param $num
     * @return mixed
     */
    public function userNum($id, $type = 'add', $num = 1)
    {
        $option = $type == 'add' ? 'increment' : 'decrement';
        $info   = $this->where('id', $id)->first();
        if (isset($info['user_num']) && $info['user_num'] <= 0 && $type == 'del') {
            return true;
        }
        if (isset($info['level']) && $info['level'] == 1) {
            $this->where('id', $id)->$option('user_num', $num);
        }
        if (isset($info['level']) && $info['level'] == 2) {
            $this->where('id', $info['pid'])->$option('user_num', $num);
            $this->where('id', $id)->$option('user_num', $num);
        }
        // level =3 扩展 todo
    }

    /**
     * 更新数据
     * @param array $param
     * @param array $data
     * @return mixed
     */
    public function updateField($param = [], $data = [])
    {
        return $this->where($param)->update($data);
    }

    /**
     * 判断数据是否存在
     * @param $where
     * @return mixed
     */
    public function isExist($where = [])
    {
        return $this->where($where)->first();
    }


    /**
     * 获取部门列表
     * @param $where
     * @param $field
     * @return mixed
     */
    public function getDepartmentList($where = [], $field = ['*'])
    {
        $query = $this->select($field);
        if (!empty($where["dep_id"])) {
            $query->whereIn('dep_id', $where["dep_id"]);
            unset($where["dep_id"]);
        }

        if (!empty($where['dep_pid'])) {
            $query->where('dep_pid', $where['dep_pid']);
            unset($where['dep_pid']);
        }

        $query->where($where);
        return $query->get()->toArray();
    }

    /**
     * 根据 dep_id 查找所有子部门 ID（支持多个 dep_id）
     *
     * @param array $depIds 部门 ID 数组
     * @return array 所有子部门 ID 的数组
     */
    public function getAllChildDepartmentIds(array $depIds)
    {
        // 存储所有子部门 ID
        $childIds = [];

        // 遍历每个 dep_id，递归查找子部门
        foreach ($depIds as $depId) {
            self::findChildDepartments($depId, $childIds);
        }

        // 去重后返回
        return array_unique(array_merge($childIds, $depIds));
    }

    /**
     * 递归查找子部门
     *
     * @param int $parentId 父部门 ID
     * @param array &$childIds 存储子部门 ID 的数组
     */
    private static function findChildDepartments($parentId, &$childIds)
    {
        // 查找直接子部门
        $children = Department::where('dep_pid', $parentId)->get();

        foreach ($children as $child) {
            // 将子部门 ID 添加到结果数组
            $childIds[] = $child->dep_id;

            // 递归查找子部门的子部门
            self::findChildDepartments($child->dep_id, $childIds);
        }
    }

    /**
     * 查询部门总监或组长ID
     * @param int $user_id
     * @param int $type
     * @return mixed
     */
    public function getManageGroupIds(int $user_id, int $type = 1)
    {
        $tags = [1, 3, 5];
        if ($type == 1) {
            $tags = [2, 4, 6];
        }

        $model = $this->getModel();
        return $model->whereIn('tag', $tags)->where(function ($model) use ($user_id) {
            $model->where('first_admin_id', $user_id)->orWhere('second_admin_id', $user_id);
        })->pluck('dep_id')->toArray();
    }
}
