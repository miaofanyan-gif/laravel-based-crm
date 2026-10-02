<?php
namespace Common\Permissions\Models;

use Illuminate\Database\Eloquent\SoftDeletes;


class Menu extends BaseModel
{

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
        'pid', 'label', 'ico', 'index', 'create_user', 'update_user', 'sort', 'state', 'subsystem_num'
    ];

    //功能权限
    const TYPE_AUTHORIZE = 2;
    //菜单权限
    const TYPE_MENU = 1;

}
