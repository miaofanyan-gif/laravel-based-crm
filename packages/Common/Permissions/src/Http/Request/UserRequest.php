<?php

namespace Common\Permissions\Http\Request;

use Illuminate\Validation\Rule;

class UserRequest extends BaseRequest
{

    public $rules = [];
    //添加验证
    protected $add_rules = [
        'name'      => 'required|min:2|unique:users,name',
        'area'      => 'required|integer|between:0,10',
        'role_data' => 'required|json',
    ];
    //编辑
    protected $up_user_rules = [
        'id'        => 'required|integer',
        'name'      => 'required|min:2|unique:users,name',
        'area'      => 'required|integer|between:0,10',
        'role_data' => 'required|json',
    ];
    //详情
    protected $details_rules = [
        'user_id' => 'required',
    ];

    //修改密码验证
    protected $update_pwd_rule = [
        'id'       => 'required|integer',
        'password' => 'required|min:6|regex:/^(?![^a-z]+$)(?!\D+$).{6,}$/',
    ];

    //发送邮箱
    protected $send_email_rule = [
        'email' => 'required|email',
    ];

    //忘记密码验证
    protected $reset_password_rule = [
        'email'    => 'required|email',
        'password' => 'required|min:6|regex:/^(?![^a-z]+$)(?!\D+$).{6,}$/',
        'code'     => 'required|integer'
    ];

    //用户状态
    protected $status_rule = [
        'user_id' => 'required|integer',
        'status'  => 'required|integer|in:0,1,-1'
    ];

    protected array $create_user_rules = [
        'name'          => 'required|string|max:255' ,
        'password'      => 'required|min:6|regex:/^(?![^a-z]+$)(?!\D+$).{6,}$/',
        'department_id' => 'required',
        'status'        => 'required|in:0,1',
//        'mobile'        => 'required',
    ];

    //验证字段名称
    public $strings_key = [
        'id'            => '用户id',
        'user_id'       => '用户id',
        'email'         => '邮箱',
        'password'      => '密码',
        'role_id'       => '角色',
        'name'          => '姓名',
        'birthday'      => '日期',
        'sex'           => '性别',
        'phone'         => '手机号',
        'area'          => '地区',
        'group_id'      => '组id',
        'department_id' => '部门id',
        'status'        => '状态',
        'code'          => '验证码',
        'role_data'     => '角色数组',
        'mobile'        => '手机号'
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
            case $prefix . '/create'://创建用户
                $rules = $this->add_rules;
                break;
            case $prefix . '/update'://编辑用户
                $rules         = $this->up_user_rules;
                $rules['name'] = $rules['name'] . ',' . $this->get('id');
                break;
            case $prefix . '/delete'://删除
            case $prefix . '/portrait'://上传头像
                $rules = $this->details_rules;
                break;
            case $prefix . '/status'://状态
                $rules = $this->status_rule;
                break;
            case $prefix . '/password'://修改密码
                $rules = $this->update_pwd_rule;
                break;
            case '/user/reset/password'://忘记密码
                $rules = $this->reset_password_rule;
                break;
            case '/user/send/mail'://发送邮箱验证码
                $rules = $this->send_email_rule;
                break;
            case $prefix . '/handle': // 添加、编辑用户
                $rules = $this->create_user_rules;
                break;
            default://默认验证
                $rules = $this->rules;
        }
//        Common::printR($rules);
        return $rules;
    }


}
