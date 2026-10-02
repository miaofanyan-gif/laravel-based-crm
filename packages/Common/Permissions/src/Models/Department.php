<?php
namespace Common\Permissions\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Common\Libs\Common;
use Common\Permissions\Models\DepartmentsUsers;

class Department extends BaseModel{

    // 开启软删除
    use SoftDeletes;
    // 软删除标识字段
    protected $dates = ['deleted_at'];

    protected $casts = [
        'child_ids' => 'array'
    ];

    //查询条件
    public $options= [
        'pid' => ['type' => '='],//子集
        'level' => ['type' => '='],//等级
        'company_id' => ['type' => '='],//企业
        'name' => ['type' => 'like','field'=>['name']],//搜索关键字
    ];

    public $defaultOrder = ['updated_at,desc|id,desc'];//默认排序方式

    //列表显示字段
    public $listFields = ['id','name','pid','type','level','user_num','created_at','is_lower_level','remark'];

    public $userAdminList = ['userAdminList'];

    //部门信息
    public function userAdminList(){
        $result = $this->hasMany('Common\Permissions\Models\User','department_id','id')->select([
            'id','name'
        ]);
        //return User::where('is_admin',1)->where('department_id',16)->select(['id','name']);
        //echo $this->toSql();die;
        return $result;
    }

    public function departmentUser()
    {
        return $this->hasMany(DepartmentsUsers::class,'department_id','dep_id');
    }

    /**
     * 获取某个部门及其所有子部门的总人数
     *
     * @param string $rootDepId  uc_oa_departments.dep_id
     * @return int
     */
    public function countSubDepartmentUsers(string $rootDepId): int
    {
        /* 1. 一次性取出全表，按 dep_id 索引 */
        $all = $this->all(['id', 'dep_id', 'pid'])
            ->keyBy('dep_id');

        Common::printR($all->all());

        /* 2. 递归收集所有子 dep_id（含自己） */
        $allIds = $this->collectChildren([$rootDepId], $all);

        if ($allIds->isEmpty()) {
            return 0;
        }

        /* 3. 一条 SQL 统计人数 */
        return User::where(function ($q) use ($allIds) {
            // 把 department_ids 按 FIND_IN_SET 拆匹配
            foreach ($allIds as $depId) {
                $q->orWhereRaw("FIND_IN_SET(?, department_ids)", [$depId]);
            }
        })
            ->distinct()     // 同一人可能挂在多个子部门，去重
            ->count('id');
    }

    /**
     * 递归收集 dep_id 集合（含自身）
     */
    private function collectChildren(array $ids,  $all)
    {
        $next = collect();
        foreach ($ids as $id) {
            $children = $all->where('pid', $id)->pluck('dep_id');
            $next = $next->merge($children);
        }
        // 递归直到没有新节点
        return $next->isEmpty()
            ? collect($ids)
            : collect($ids)->merge($this->collectChildren($next->all(), $all));
    }
}
