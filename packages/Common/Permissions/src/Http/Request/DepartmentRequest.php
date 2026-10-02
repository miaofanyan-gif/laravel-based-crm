<?php

namespace Common\Permissions\Http\Request;

use Common\Libs\Common;
use Illuminate\Http\Request;
use Common\Bootstrap\BaseRequest;

class DepartmentRequest extends BaseRequest
{

    /**
     * 添加专辑
     * @var string[]
     */
    protected $add_rules = [
        'name' => 'required|string',
        'pid'  => 'required|integer',
    ];

    /**
     * 部门列表
     * @var string[]
     */
    protected $list_rules = [
        'name' => 'string',
    ];
    /**
     * 修改部门
     * @var string[]
     */
    protected $edit_rules = [
        'id'  => 'required|integer',
        'name'   => 'required|string',
        'pid'    => 'required|integer',
        'type'   => 'nullable',
        'remark' => 'nullable',
    ];

    protected array $edit_depart_tag = [
        'id' => 'required|integer',
        'tag' => 'required',
    ];

    protected array $update_rules = [
        'id'     => 'required|integer',
        'status' => 'required|in:1,-1'
    ];


    //验证字段名称
    public $strings_key = [
        'id'     => '部门id',
        'pid'    => '上级部门id',
        'name'   => '部门名称',
        'type'   => '部门类型',
        'remark' => '备注',
        'status' => '状态',
        'tag'    => '标签'
    ];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $prefix = request()->route()->getPrefix();

        $prefix = "/" . $prefix;

        // 根据不同的情况, 添加不同的验证规则
        switch ($this->getPathInfo()) {
            case $prefix . '/update'://更新部门
                $rules = $this->edit_rules;
                break;
            case $prefix . '/updateDepart'://更新部门
                $rules = $this->edit_depart_tag;
                break;
            case $prefix . '/status'://启用、禁用
                $rules = $this->update_rules;
                break;
            default://默认验证
                $rules = $this->rules;
        }
//        Common::printR($rules); die();
        return $rules;
    }
}
