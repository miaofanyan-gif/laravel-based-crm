<?php
namespace Common\Permissions\Models;

use Illuminate\Database\Eloquent\SoftDeletes;



class Role extends BaseModel{
    /*
     * 拥有超级权限的角色/todo 改成角色标签
     */
    static $superRoles = [1];

    //销售助理角色ID
    static $saleAssistantRoleID = 198;

    // 开启软删除
    use SoftDeletes;
    // 软删除标识字段
    protected $dates = ['deleted_at'];
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'state', 'index','parent_id','desc', 'media_ids', 'data_permission'
    ];

    protected $casts = [
        'data_permission' => 'json',
        'manage_role_ids' => 'array'
    ];

    //查询条件
    public $options= [
        'name' => ['type' => 'like' ,'field'=>['name','id']],//名称搜索
        'index' => ['type' => '='],//标识
        'state' => ['type' => 'status'],//状态
    ];

    public $defaultOrder = ['id,desc'];//默认排序方式

    //列表显示字段
    public $listFields = [
        'id', 'name', 'index', 'state', 'created_at','parent_id','desc', 'media_ids', 'data_permission'
    ];

    public function menu(){
        return $this->hasMany('Common\Permissions\Models\RoleMenu', 'role_id', 'id');
    }
    public function authority(){
        return $this->hasMany('Common\Permissions\Models\RoleAuthority', 'role_id', 'id');
    }

}
