<?php


namespace Common\Bootstrap;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Common\Bootstrap\Traits\ResponseTrait;
use Common\Libs\Common;
use Common\Libs\FileCsvExport;
use JoyGc\Dashboard\Models\IndexReport;

class BaseClickHouseController extends Controller
{

    use ResponseTrait;

    public $models;
    public $repository;
    public $header = [];
    public $export_name = '报表';

    public function __construct($repository)
    {
        $this->models = $repository->getModel();
        $this->repository = $repository;
    }

    /**
     * 列表页面
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $result = $this->list($request);
        return $this->ResponseSucess($result);
    }

    /**
     * 获取筛选条件,跟排序
     * @param $query
     * @param $data
     * @return mixed
     */
    public function getOptions($query,$data)
    {
        $options = $data['options']??$this->models->options;
        $data = IndexReport::oldToNew($data);
        collect($options)->map(function ($item, $key) use (&$query, $data) {
            switch ($item['type']) {
                case 'polymerize'://只针对总经理
                    if (empty($data[$key])) break;
                    $query = $query->where(function ($query) use ($item, $data) {//组合查询条件
                        if (is_array($item['field']) && count($item['field']) >= 2) {
                            foreach ($item['field'] as $key => $value) {
                                if (!empty($data[$item['type']][$value])) {
                                    $query->orWhereIn($value, $data[$item['type']][$value]);
                                }
                            }
                        } else if ($item['field'] == 'manage_id') {
                            $query->where('manage_id', '=', (int)($data['polymerize']['manage_id']));
                        } else if ($item['field'] == 'sale_id') {
                            $query->whereIn('sale_id', array_map(function ($value) {
                                return intval($value);
                            }, $data['polymerize']['sale_id']));
                        }
                    });
                    break;
                case 'polymerize_optimize'://
                    if (!isset($data[$key]) || empty($data[$key])) break;
                    $query = $query->where(function ($query) use ($item, $data) {//组合查询条件
                        if (is_array($item['field']) && count($item['field']) == 2) {
                            $query->whereIn('manage_id', array_map(function ($value) {
                                return intval($value);
                            }, $data['polymerize_optimize']['manage_id']));
                            $query->orWhereIn('group_id', array_map(function ($value) {
                                return intval($value);
                            }, $data['polymerize_optimize']['group_id']));
                        } else if ($item['field'] == 'manage_id') {
                            $query->whereIn('manage_id', array_map(function ($value) {
                                return intval($value);
                            }, $data['polymerize_optimize']['manage_id']));
                        } else if ($item['field'] == 'group_id') {
                            $query->whereIn('group_id', array_map(function ($value) {
                                return intval($value);
                            }, $data['polymerize_optimize']['group_id']));
                        }
                    });
                break;
                case 'like':
                    if (!isset($data[$key]) || empty($data[$key])) break;
                    //传输参数值
                    $keyword = $data[$key];
                    //查询字段-无限制
                    $field = isset($item['field'])?$item['field']:[$key];
                    $query = $query->where(function ($query) use ($field, $keyword) {//组合查询条件
                        //循环所查询字段进行组合条件
                        collect($field)->map(function ($val, $k) use (&$query, $field, $keyword) {
                            //组合条件为 and( a like X or b like X or ....)
                            $query->orWhere($val, 'LIKE', '%' . $keyword . '%');
                        });
                    });
                    break;
                case 'between':
                    if (isset($data[$key . '_start']) && !empty($data[$key . '_start'])) {
                        $query = $query->where($key, '>=', $data[$key . '_start'] . ' 00:00:00');
                    }
                    if (isset($data[$key . '_end']) && !empty($data[$key . '_end'])) {
                        $query = $query->where($key, '<=', $data[$key . '_end'] . ' 23:59:59');
                    }
                    break;
                case 'between_date'://不带时分秒
                    if (isset($data[$key . '_start']) && !empty($data[$key . '_start'])) {
                        $query = $query->where($key, '>=', $data[$key . '_start'] );
                    }
                    if (isset($data[$key . '_end']) && !empty($data[$key . '_end'])) {
                        $query = $query->where($key, '<=', $data[$key . '_end']);
                    }
                    break;
                case 'between_dates'://时间数组
//                    Common::printR($data);
                    if (isset($data[$key]) && !empty($data[$key])) {
                        if (count($data[$key]) == 2)
                            $query = $query->where($key, '>=', $data[$key][0])->where($key, '<=', $data[$key][1]);
                        else
                            $query = $query->where($key, '>=', $data[$key][0]);
                    }
                    break;
                case 'betweens':
                    if (!empty($data[$key])) {
                        if (count($data[$key]) > 1)
                            $query = $query->whereBetween($key, $data[$key]);
                        else
                            $query = $query->where($key, '>=', $data[$key][0]);
                    }
                    break;
                case 'in':
                    if (!isset($data[$key]) || empty($data[$key])) break;
                    $query = $query->whereIn($key, $data[$key]);
                    break;
                case 'intIn':
                    if (!isset($data[$key]) || empty($data[$key])) break;
//                    Common::printR($data[$key]);
                    $query = $query->whereIn($key,array_map(function ($value){
                        return intval($value);
                    },is_numeric($data[$key])?(array)$data[$key]:$data[$key]) );
                    break;
                case 'notIn':
                    if (!isset($item['value']) || empty($item['value'])) break;
                    $query = $query->whereNotIn($item['field'], array_map(function ($value) {
                        return intval($value);
                    }, $item['value']));
                    break;
                case 'gte':
                    if (!isset($data[$key]) || empty($data[$key])) break;
                    $query = $query->where($key,'>=', $data[$key]);
                    break;
                case 'with':
                    break;
                case 'notNull':
                    if (!isset($data[$key]) || empty($data[$key])) break;
                    $query = $query->whereNotNull($key);
                    break;
                default :
                    if (!isset($data[$key]) || empty($data[$key])) break;
                    $query = $query->where($key, is_numeric($data[$key]) ? (int)$data[$key] : $data[$key]);//clickhouse 踩过的坑，如果库中用int 查询也需用int类型
                    break;
            }
        });
//        echo $query->toSql();
        return $query;
    }


    /**
     * 排序
     * @param $query
     * @param $order
     * @return mixed
     * 例子 order=created_at,asc|user_id
     */
    public function order($query, $order)
    {
        $order = $order ? explode('|', $order) : $this->models->defaultOrder;
        //排序
        !empty($order) and collect($order)->each(function ($item, $key) use (&$query) {
            $order = explode(',', $item);
            $type = isset($order[1]) && $order[1] == 'asc' ? 'ASC' : 'DESC';
            $item and $query = $query->orderBy($order[0], $type);
        });
        return $query;
    }

    /**
     * 聚合
     * @param $query
     * @param $group
     * @return mixed
     * 例子 order=created_at,asc|user_id
     */
    public function group($query, $group)
    {
        $group = $group ? $group : ($this->models->defaultGroup??[]);
        if($group){
            $query = $query->groupBy($group);
        }
        return $query;
    }


    /**
     * 列表
     * @param $request
     * @return mixed
     */
    public function list($request)
    {
        $filed = $this->models->listFields?:$this->models->getListField($request->get('field',[]));
        $query = $this->models->getCurrentModel()->select($filed);
        $query = $this->getOptions($query,$request->all());
        switch ($request->get('data_type', 'list')) {
            case  'export':
                $query = $this->order($query, $request->get('order'));
                $query = $this->dbHaving($query,$request->get('having_data',[]));
                $query = $this->group($query, $request->get('group'));
                Common::handleExportCsv($this->handleList($query->get()), $this->header, $request->get('date', $request->get('month')), $this->export_name);
                $data = [];
                break;
            case  'total':
                $data = [];
                $data = $query->first();
                break;
            case 'list':
                $query = $this->group($query, $request->get('group'));
                $page = $request->get('page', 1);
                $limit = !empty($request->get('per_page')) ? $request->get('per_page') : $request->get('limit', 10);
                // 添加 HAVING 子句
                $query = $this->dbHaving($query,$request->get('having_data',[]));
                $total = $query->count();//总条数
                $query = $this->order($query, $request->get('order'));
                $data['header'] = Common::handleHeader($this->header);
                $data['data'] = $query->limit($limit, ($page - 1) * $limit)->get();
                $data['sql'] = $query->toSql();
                $data['page_info'] = Common::pageHandle($page, $limit, $total);//分页处理
                $data = $this->handleList($data);
                break;
            case 'cost':
                $query = $this->order($query, $request->get('order'));
                $query = $this->group($query, $request->get('group'));
                $limit = $request->get('limit', 100);
                $data = $query->limit($limit, 0)->get();
                $data = $this->handleList($data);
//                echo $query->toSql();
                break;
            case  'more_export':
                $query = $this->order($query, $request->get('order'));
                $query = $this->dbHaving($query, $request->get('having_data', []));
                $query = $this->group($query, $request->get('group'));

                $date = $request->get('date', $request->get('month'));
                $file_csv = new FileCsvExport($this->header,$date,$this->export_name);
                $file_csv->writeCsvHeader();
                $total = $query->count();//总条数
                $limit = 1000;
                $last_page = ceil($total / $limit);//最后一页
                for ($page = 1; $page <= $last_page; $page++) {
                    $data = $query->limit($limit, ($page - 1) * $limit)->get();
                    $data = $this->handleList($data);
                    \Log::info('查询的页数',[$page,$last_page]);
                    $file_csv->writeCsvBody($data);
                }
                $file_csv->writeCsvFooter();
            default:
                $data = [];
        }
        return $data;
    }

    public function dbHaving($query, $data=[])
    {
        if (empty($data)){
            return $query;
        }
        foreach ($data as $key => $value){
            foreach ($value as $oper => $operValue){
                $query = $query->having($key, $operValue[0], $operValue[1]);
            }
        }
        return $query;
    }

    /**
     * 导出
     * @param $request
     * @return mixed
     */
    public function export(Request $request)
    {
        $filed = $this->models->listFields?:$this->models->getListField($request->get('group',[]));
        $query = $this->models->getCurrentModel()->select($filed);
        $query = $this->getOptions($query,$request->all());
        $query = $this->order($query, $request->get('order'));
        $query = $this->group($query, $request->get('group'));
        $result = $query->get();
        Common::handleExportCsv($result,$this->header,$request->get('date',$request->get('month')),$this->export_name);
    }

    /**
     * 查询数据详情
     * @param $request
     * @return mixed
     */
    public function detail($request)
    {
        $query = $this->models->getCurrentModel()->select($this->models->listFields);
        $query = $this->getOptions($query,$request->all());
        $query = $this->order($query, $request->get('order'));
        return $query->first();
    }

    /**
     * 设置 Repository
     * @param $repository
     */
    public function setRepository($repository)
    {
        $this->repository = $repository;
        $this->models = $repository->getModel();
    }


    /**
     * 处理分页数据
     * @param $data
     * @return mixed
     */
    public function handleList($data)
    {
        return $data;
    }

}
