<?php
namespace Common\Permissions\Http\Request;
use Common\Bootstrap\BaseRequest;
use Common\Permissions\Common\Common;

class MenuRequest extends BaseRequest{

    public $rules = [];

    // 创建菜单验证
    protected $addMenuRule=[
        'pid' => 'required|integer',
        'label' => 'required|between:2,50|string',
        'ico' => 'string|nullable',
        'index' => 'nullable|string|between:1,50',
        'sort' => 'nullable|integer|between:0,10000',
        'state' => 'integer|between:0,1',
        // 必须显式传（哪怕是空字符串代表"系统设置/共享"），不允许漏传静默落到默认值——用 present 而不是
        // required，因为 required 会把合法的空字符串当成"没传"拒绝掉
        'system_num' => 'present|string'
    ];

    // 修改菜单验证
    protected $upMenuRule=[
        'id' => 'required|integer',
        'pid' => 'required|integer',
        'label' => 'required|between:2,50|string',
        'ico' => 'nullable|string',
        'index' => 'nullable|string|between:1,50',
        'sort' => 'nullable|integer|between:0,10000',
        'state' => 'integer|between:0,1'
    ];


    // 删除菜单
    protected $delMenuRule=[
        'id' => 'required|integer'
    ];


    //验证字段名称
    public $strings_key = [
        'id' => '菜单id',
        'pid' => '父级id',
        'label' => '菜单',
        'ico' => '图标',
        'index' => '标识',
        'sort' => '排序',
        'state' => '状态'
    ];


    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $prefix = request()->route()->getPrefix();
        $prefix = '/' . $prefix;
        // 根据不同的情况, 添加不同的验证规则
        switch($this->getPathInfo()){
            case $prefix.'/create'://创建用户
                $rules = $this->addMenuRule;
                break;
            case $prefix.'/update'://编辑用户
                $rules = $this->upMenuRule;
                break;
            case $prefix.'/delete'://删除
                $rules = $this->delMenuRule;
                break;
            default://默认验证
                $rules = $this->rules;
        }
        return $rules;
    }

}
