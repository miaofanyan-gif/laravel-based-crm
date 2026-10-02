<?php

namespace Common\Permissions\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends BaseModel implements AuthorizableContract, AuthenticatableContract, JWTSubject
{
    use Authenticatable, Authorizable;

    // 开启软删除
    use SoftDeletes;

    // 软删除标识字段
    protected $dates = ['deleted_at'];
    
    //超管角色id
    static $superman = 1;

    const SUPER_DAMIN = 'super_admin';
    //已授权
    const IS_AUTHORIZED = 1;

    //数据权限类型
    const PERMISSION_TYPE_SELF                 = 'self';
    const PERMISSION_TYPE_ALL                  = 'all';
    const PERMISSION_TYPE_SPECIFIC_USERS       = 'specificUsers';
    const PERMISSION_TYPE_SPECIFIC_DEPARTMENTS = 'specificDepartments';
    const PERMISSION_TYPE_DEPARTMENT_AND_SUB   = 'departmentAndSub';
    public static array $permissions_type = [
        self::PERMISSION_TYPE_ALL                  => '全部',
        self::PERMISSION_TYPE_DEPARTMENT_AND_SUB   => '本部门及下属部门',
        self::PERMISSION_TYPE_SPECIFIC_DEPARTMENTS => '指定部门(包含下级部门)',
        self::PERMISSION_TYPE_SELF                 => '本人',
//        self::PERMISSION_TYPE_SPECIFIC_USERS       => '指定用户',
    ];


    //区域
    static $area = [
        0  => '未知',
        3  => '北京',
        1  => '上海',
        2  => '广州',
    ];

    //已离职
    const STATUS_DEPARTURE = 2;
    //状态
    static $status = [
        0 => '禁用',
        1 => '启用',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
         'email', 'password', 'role_id', 'name', 'last_login', 'last_ip', 'phone', 'area',
        'department_id', 'status', 'created_at', 'updated_at', 'operator_name', 'userid', 'mobile', 'head_img', 'unionid', 'title'
    ];

    public $with = ['role', 'departments'];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'media_id'        => 'array',
        'data_permission' => 'json',
        'sale_info'       => 'json',
    ];

    //列表显示字段
    public $listFields = [
        'id', 'role_id', 'password', 'name', 'email', 'status', 'mobile', 'unionid','openid',
        'created_at', 'last_login', 'head_img', 'area', 'userid', 'title', 'crm_staff_id', 'data_permission', 'media_id'
    ];

    protected $appends = [
        'area_name',
        'competentInfo',
        'department_name',
        'parent_departments',
        'user_areas'
    ];

    /**
     * 获取上级信息
     * @param $value
     * @return array|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function getCompetentInfoAttribute($value)
    {
        $competentInfo = [];
        $userId        = $this->attributes['userid'] ?? '';
        if (!empty($userId)) {
            $departmentIds = DepartmentsUsers::query()->where('userid', $userId)->pluck('department_id')->toArray();
            $competentInfo = DepartmentsUsers::query()->select(['departments_users.userid', 'departments_users.name', 'departments_users.department_id', 'users.area', 'users.id', 'users.email', 'users.unionid', 'departments_users.title'])->leftJoin('users', 'departments_users.userid', 'users.userid')->whereIn('departments_users.department_id', $departmentIds)->where('departments_users.leader', 1)->first();
            if ($competentInfo) {
                $competentInfo['area_name'] = self::$area[$competentInfo['area'] ?? 0] ?? '';
            }
        }
        return $this->attributes['competentInfo'] = $competentInfo;
    }

    /**
     * 获取区域名称
     * @return string
     */
    public function getAreaNameAttribute()
    {
        return $this->attributes['area_name'] = self::$area[$this->attributes['area'] ?? 0] ?? '';
    }

    /**
     * 部门名称
     * @return string
     */
    public function getDepartmentNameAttribute()
    {
        $user_id         = $this->attributes['userid'] ?? '';
        $deparmtentNames = '';
        if ($user_id) {
            $department_id   = DepartmentsUsers::query()->where('userid', $user_id)->pluck('department_id')->toArray();
            $deparmtentNames = Department::query()->whereIn('dep_id', $department_id)->pluck('name')->toArray();
            $deparmtentNames = implode(',', $deparmtentNames);
        }

        return $this->attributes['department_name'] = $deparmtentNames;
    }

    public function getUserAreasAttribute()
    {
        return AreaUser::query()->where('user_id', $this->attributes['id'] ?? 0)->pluck('area_id')->toArray();
    }

    /**
     * 上级部门名称
     * @return string
     */
    public function getParentDepartmentsAttribute()
    {
        $user_id = $this->attributes['userid'] ?? '';
        $result  = [];
        if ($user_id) {
            $depIds = DepartmentsUsers::query()->where('userid', $user_id)->pluck('department_id')->toArray();
            if (!is_array($depIds)) {
                $depIds = [$depIds];
            }

            // 遍历每个部门 ID
            foreach ($depIds as $depId) {
                $this->recursiveFindParent($depId, $result);
            }
        }

        return $this->attributes['parent_departments'] = $result;
    }

    /**
     * 递归查找父级部门
     *
     * @param int $depId 部门 ID
     * @param array &$result 结果数组
     */
    private function recursiveFindParent($depId, &$result)
    {
        // 查询当前部门信息
        $department = Department::query()
            ->where('dep_id', $depId)
            ->first();

        if (!$department) {
            return; // 如果部门不存在，结束递归
        }

        // 将当前部门信息添加到结果中
        $result[] = [
            'dep_id'  => $department->dep_id,
            'dep_pid' => $department->dep_pid,
            'name'    => $department->name,
            'id'      => $department->id,
        ];

        // 如果父级部门 ID 是 968919182(商务服务事业部) 或 1，结束递归
        if ($department->dep_pid == 968919182 || $department->dep_pid == 1) {
            return;
        }

        // 递归查找父级部门
        $this->recursiveFindParent($department->dep_pid, $result);
    }


    // 钉钉部门信息
    public function departments()
    {
        return $this->belongsToMany(
            'Common\Permissions\Models\Department',
            'Common\Permissions\Models\DepartmentsUsers',
            'userid',
            'department_id',
            'userid',
            'dep_id'
        )
            ->where('departments.deleted_at', null) // 过滤已删除的部门
            ->select(['departments.id', 'departments.name', 'departments.type','departments.tag','departments.dep_id', 'departments.dep_pid','departments.first_admin_id', 'departments.second_admin_id']);
    }



    /**
     * 角色信息
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function role()
    {
        return $this->belongsToMany(
            'Common\Permissions\Models\Role',        // 目标模型
            'Common\Permissions\Models\RoleUser',    // 中间表
            'user_id',                                  // 中间表中与用户表关联的键
            'role_id',                                  // 中间表中与角色表关联的键
            'id',                                       // 用户表主键
            'id'                                // 角色表主键
        )->where('roles.deleted_at', null)->select(['roles.id', 'roles.name', 'roles.index', 'roles.media_ids', 'roles.data_permission', 'manage_role_ids']); // 指定返回的字段
    }


    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return ['state_at' => $this->state_at];
    }

    /**
     * 用户判重
     *
     * @return int
     */
    public function heavySentence($data)
    {
        return $this->select(['id'])->where(['email' => $data['email']])->count();
    }
}
