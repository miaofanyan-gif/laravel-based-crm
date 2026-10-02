<?php

namespace Common\Permissions\Http\Request;

use Common\Bootstrap\BaseRequest;
use Common\Permissions\Common\Common;

class RoleRequest extends BaseRequest
{

    public $rules = [
        'id' => 'required|integer',
    ];
    //添加验证
    protected $addRules = [
        'name'      => 'required|string',
        'desc'      => 'nullable',
        'parent_id' => 'required|integer',
    ];

    //修改角色
    protected $upRoleRule = [
        'id'        => 'required|integer',
        'name'      => 'required|string',
        'desc'      => 'nullable',
        'parent_id' => 'required|integer',
    ];

    /**
     * 设置数据权限
     * @var array|string[]
     */
    protected array $setDataPermission = [
        'id'              => 'required|integer',
        'index'           => 'required|string',
        'media_ids'       => 'nullable',
        'data_permission' => 'required'
    ];

    //修改角色
    protected $stateRoleRule = [
        'id'   => 'required|integer',
        'name' => 'required|string',
        'desc' => 'nullable',
    ];

    //删除角色
    protected $delete_rules = [
        'id' => 'required',
    ];

    //设置角色权限
    protected $setPermissionRule = [
        'id'             => 'required|integer',
//        'permissionData' => 'required|array'
    ];


    //验证字段名称
    public $strings_key = [
        'name'               => '名称',
        'state'              => '状态',
        'rm_id'              => '菜单',
        'ra_id'              => '功能',
        'id'                 => '角色id',
        'status'             => '状态',
        'parent_id'          => '父级角色ID',
        'desc'               => '角色描述',
        'authorize_menu_ids' => '菜单权限id',
        'index'              => '角色类型',
        'media_ids'          => '媒体',
        'data_permission'    => '数据权限',
        'permissionData'     => '权限信息',
    ];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $prefix = request()->route()->getPrefix();
        // 根据不同的情况, 添加不同的验证规则
        $prefix = "/" . $prefix;
        switch ($this->getPathInfo()) {
            case $prefix . '/create'://创建角色
                $rules = $this->addRules;
                break;
            case $prefix . '/update'://编辑角色
                $rules = $this->upRoleRule;
                break;
            case $prefix . '/status'://状态
                $rules = $this->stateRoleRule;
                break;
            case $prefix . '/delete'://删除角色
                $rules = $this->delete_rules;
                break;
            case $prefix . '/set/permission'://设置角色权限
                $rules = $this->setPermissionRule;
                break;
            case $prefix . '/set/data/permission'://设置角色数据权限
                $rules = $this->setDataPermission;
                break;
            default://默认验证
                $rules = $this->rules;
        }
        return $rules;
    }

}
