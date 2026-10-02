<?php

namespace Common\Permissions\Http\Request;

use Illuminate\Foundation\Http\FormRequest;
use Common\Permissions\Traits\ResponseTrait;

class BaseRequest extends FormRequest
{

    use ResponseTrait;
    //添加需要验证的字段
    protected $rules = [

    ];
    //可以定义全部字段
    protected $strings_key = [

    ];
    //定义验证方式
    public $strings_val = [
        'required'=> '为必填项',
        'email'=> '必须为邮箱格式',
        'min'=> '最小为:min',
        'max'=> '最大为:max',
        'between'=> '长度在:min和:max之间',
        'integer'=> '必须为整数',
        'in'=> '参数不正确',
        'int'=> '必须为整型',
        'string' => '必须为字符串',
        'numeric'=> '必须为数字',
        'array'=> '必须为数组',
        'sometimes'=> '',
        'date'=> '日期格式不正确',
        'date_format'=> ':attribute 与给定的格式 :format 不符合',
        'after'=> '之后',
        'after_or_equal'=> '之后或者等于',
        'required_if'=> '条件必填项',
        'nullable' => '条件必填项',
        'unique' => '不能重复或已经存在',
        'required_without_all' => ':attribute 字段是必须的当前没有一个 :values 是存在的',
        'json' => '数据格式为json',
        'url' => ':attribute 无效的url格式',
        'digits_between' => '必须在 :min 和 :max 位之间',
        'regex' => ':attribute 格式是无效的',
    ];

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;//修改为true，必须为true
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = $this->rules;
//        // 根据不同的情况, 添加不同的验证规则
//        if (Request::getPathInfo() == '/save')//如果是save方法
//        {
//            $rules['Student.addr'] = 'sometimes';
//        }
//        if (Request::getPathInfo() == '/edit')//如果是edit方法
//        {
//            $rules['Student.addr'] = 'required|min:5';
//        }
        return $rules;

    }
    //返回错误信息
    public function messages(){
        $rules = $this->rules();
        $k_array = $this->strings_key;
        $v_array = $this->strings_val;
        foreach ($rules as $key => $value) {
            $new_arr = explode('|', $value);//分割成数组
            foreach ($new_arr as $k => $v) {
                $head = strstr($v,':',true);//截取:之前的字符串
                if ($head) {$v = $head;}
                $array[$key.'.'.$v] = $k_array[$key].$v_array[$v];
            }
        }
        return $array;
    }

}
