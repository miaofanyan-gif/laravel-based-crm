<?php

namespace Common\Bootstrap;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use DateTimeInterface;
use Common\Libs\Common;

class BaseClickHouseModel extends Model
{


    //列表显示字段
     public $listFields = ['*'];

    //列表查询条件
    /***
     * @var array
     *     public $options= [
            'user' => ['type' => 'like','field'=>['id','name']],
            'menu_id' => ['type' => '='],
            'menu' => ['type' => 'like'],
            'created_at' => ['type' => 'between']
           ];
     * key和value解释
     * 例如 数组中user(接口或前端接口参数)，type 查询类型 field 数据库所要查询对应的字段
     * 例如 model中定义
        public $options= [
            'name' => ['type' => 'like','field'=>['advertiser_id','name','service_id']],
        ];
        根据根据接收到参数，组合查询条件为  and (advertiser_id like "%123%" or name like "%123%" or service_id like "%123%")
     */
    public $options = [];
    /**
     * 列表默认排序
     * ['user_id,desc','created,asc']
     **/
    public $defultOrder = [];
    /**
     * 列表默认分组
     **/
    public $defultGroup = [];
    /**允许排序字段
     * @var array
     * ['user_id','created_at']
     */
    public $orderFields = [];
    /**关联表
     * @var array
     * public $withList = ['actorScore'];
     */
    public $withList=[];


    /**
     * 格式化时间
     * @param DateTimeInterface $date
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * 获取当前model
     * @return mixed
     */
    public function getCurrentModel(){
        return DB::connection($this->connection)->table($this->table);
    }

    /**
     * 查询字段
     * @param array $field
     * @return array
     */
    public function getListField($field=[]){
        return $field?:[];
    }



}
