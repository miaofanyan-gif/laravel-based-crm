<?php

namespace Common\Permissions\Http\Request;

class SigninRequest extends BaseRequest
{

    //验证规则可自己添加需要验证的字段
    protected $rules = [
        'email' => 'required|email',
        'password' => 'required|min:6',
    ];
    //这里我只写了部分字段，可以定义全部字段
    protected $strings_key = [
        'email' => '用户名',
        'password' => '密码',
    ];


}
