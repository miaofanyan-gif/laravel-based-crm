<?php

namespace Common\OperateLog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Common\Bootstrap\BaseModel;


class Log extends BaseModel
{
    protected $connection = 'auth_mysql';
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
        'menu_id', 'user', 'menu', 'route', 'user_id', 'location', 'ip', 'parameters', 'return', 'company_id'
    ];
    public $listFields = [
        '*'];

    public $options = [
        'user'       => ['type' => 'like', 'field' => ['user', 'route','menu']],
        'menu_id'    => ['type' => '='],
        'menu'       => ['type' => 'like'],
        'user_id'    => ['type' => '='],
        'created_at' => ['type' => 'between']
    ];
    public $defaultOrder = ['id,desc'];

    public $orderFields = ['user_id', 'created_at'];



}
