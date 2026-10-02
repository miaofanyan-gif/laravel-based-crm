<?php
namespace Common\Permissions\Models;

use Illuminate\Database\Eloquent\SoftDeletes;


class DepartmentsUsers extends BaseModel{

    // 开启软删除
    use SoftDeletes;
    // 软删除标识字段
    protected $dates = ['deleted_at'];

    //查询条件
    public $options= [
        'name' => ['type' => '='],//子集
        'email' => ['type' => '='],//等级
    ];

    public $defaultOrder = ['updated_at,desc|id,desc'];//默认排序方式

    //列表显示字段
    public $listFields = ['id','name','email','head_img','department_id','leader','userid','title','created_at','updated_at','deleted_at'];


    public function departments()
    {
        return $this->belongsTo(\Common\Permissions\Models\Department::class,'department_id','dep_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'userid', 'userid'); // 'userid' 是关联字段
    }

}
