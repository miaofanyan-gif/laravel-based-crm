<?php

namespace Common\Bootstrap;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use DB;
use DateTimeInterface;

class BaseModel extends Model
{

    // 软删除标识字段
    protected $dates = ['deleted_at'];

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

    public $defaultOrder = [];
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

    //批量更新
    static public function updateBatch($multipleData = [])
    {

        //拼接表名
        $tableName = DB::getConfig('prefix').self::getModel()->getTable(); // 表名
        $firstRow = current($multipleData);

        $updateColumn = array_keys($firstRow);
        // 默认以id为条件更新，如果没有ID则以第一个字段为条件
        $referenceColumn = isset($firstRow['id']) ? 'id' : current($updateColumn);
        unset($updateColumn[0]);
        // 拼接sql语句
        $updateSql = "UPDATE " . $tableName . " SET ";
        $sets = [];
        $bindings = [];
        foreach ($updateColumn as $uColumn) {
            $setSql = "`" . $uColumn . "` = CASE ";
            foreach ($multipleData as $data) {
                $setSql .= "WHEN `" . $referenceColumn . "` = ? THEN ? ";
                $bindings[] = $data[$referenceColumn];
                $bindings[] = $data[$uColumn];
            }
            $setSql .= "ELSE `" . $uColumn . "` END ";
            $sets[] = $setSql;
        }
        $updateSql .= implode(', ', $sets);
        $whereIn = collect($multipleData)->pluck($referenceColumn)->values()->all();
        $bindings = array_merge($bindings, $whereIn);
        $whereIn = rtrim(str_repeat('?,', count($whereIn)), ',');
        $updateSql = rtrim($updateSql, ", ") . " WHERE `" . $referenceColumn . "` IN (" . $whereIn . ")";
        // 传入预处理sql语句和对应绑定数据
        return DB::update($updateSql, $bindings);
    }
}
