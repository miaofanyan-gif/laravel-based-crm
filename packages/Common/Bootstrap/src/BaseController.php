<?php


namespace Common\Bootstrap;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use  Common\Exceptions\ValidationException;
use Common\Bootstrap\Traits\ResponseTrait;
use Common\Libs\Common;
use Illuminate\Foundation\Http\FormRequest;

class BaseController extends Controller
{

    use ResponseTrait;

    public $repository;
    public $repositories;
    public $model;
    public $header=[];
    public $export_name='export';

    public function __construct($repository)
    {
        $this->repositories = $repository;
        $this->repository = $repository->getModel();
        $this->model = $repository->getModel();
    }

    /**
     * 列表页面
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $data = $request->all();
        //$this->selectValidate($data);
        $result = $this->list($request);
        return $this->ResponseSucess($result);
    }

    /**
     * 获取筛选条件,跟排序
     * @param $data
     * @return mixed
     */
    public function getOptions($data)
    {
        $query = $this->repository;
        $options = $data['options']??$query->options;
        collect($options)->map(function ($item, $key) use (&$query, $data) {
            switch ($item['type']) {
                case 'polymerize'://只针对总经理
                    if (!isset($data[$key]) || empty($data[$key])) break;
                    $query = $query->where(function ($query) use ($item,$data) {//组合查询条件
                        if(is_array($item['field']) && count($item['field']) == 2){
                            $query->where('manage_id', '=', $data['polymerize']['manage_id']);
                            $query->orWhereIn('sale_id', $data['polymerize']['sale_id']);
                        }else if ($item['field'] == 'manage_id'){
                            $query->where('manage_id', '=', $data['polymerize']['manage_id']);
                        }else if ($item['field'] == 'manage_ids'){
                            $query->whereIn('manage_id',  $data['polymerize']['manage_id']);
                        }else if ($item['field'] == 'sale_id'){
                            $query->whereIn('sale_id', $data['polymerize']['sale_id']);
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
                            $query->orWhere($val, 'like', '%' . $keyword . '%');
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
                case 'betweens':
                    if (!empty($data[$key])) {
                        $condition = $data[$key];
                        if (count($condition) > 1)
                            $query = $query->whereBetween($key, $condition);
                        else
                            $query = $query->where($key, '>=', $condition[0]);
                    }
                    break;

                case 'range':
                    if (!empty($data[$key])) {
                        $condition = $data[$key];
                        if (count($condition) > 1)
                            $query = $query->where($key, '>=', $condition[0])->where($key, '<=', $condition[1]);
                        else
                            $query = $query->where($key, '>=', $condition[0]);
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
                case 'in':
                    if (!isset($data[$key]) || empty($data[$key])) break;
                    $query = $query->whereIn($key, $data[$key]);
                    break;
                case 'notIn':
                    if (!isset($data[$key]) || empty($data[$key])) break;
                    $query = $query->whereNotIn($key, $data[$key]);
                    break;
                case 'gte':
                    if (!isset($data[$key]) || empty($data[$key])) break;
                    $fieldKey = empty($item['field'])?$key:$item['field'];
                    $query = $query->where($fieldKey,'>=', $data[$key]);
                    break;
                case 'lt':
                    if (!isset($data[$key]) || empty($data[$key])) break;
                    $fieldKey = empty($item['field'])?$key:$item['field'];
                    $query = $query->where($fieldKey,'<', $data[$key]);
                    break;
                case 'status'://针对状态
                    if (!isset($data[$key]) || (isset($data[$key]) && $data[$key] === '')) break;
                    $query = $query->where($key, $data[$key]);
                    break;
                case 'null'://查询空
                    if (!isset($data[$key])) break;
                    $query = $query->whereNull($data[$key]);
                    break;
                case 'eq':
                    if ((!isset($data[$key]) || empty($data[$key]) && $data[$key] == '')) break;
                    $fieldKey = empty($item['field'])?$key:$item['field'];
                    $query = $query->where($fieldKey, $data[$key]);
                    break;
                case 'with':
                    break;
                default :
                    if (!isset($data[$key]) || empty($data[$key])) break;
                    $query = $query->where($key, $data[$key]);
                    break;
            }
        });
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
        $order = $order ? explode('|', $order) : $this->repository->defaultOrder;
        //排序
        !empty($order) and collect($order)->each(function ($item, $key) use (&$query) {
            $order = explode(',', $item);
            $type = isset($order[1]) && $order[1] == 'asc' ? 'asc' : 'desc';
            $item and $query = $query->orderBy($order[0], $type);
        });
        return $query;
    }

    /**
     *关联
     * @param $query
     * @param $withName
     * @return mixed
     */
    public function with($query,$withName, $data)
    {
        $with = $this->repository->$withName ?: (method_exists($this->repository, $withName) ? $this->repository->$withName($data) : []);
        if (!empty($with)) {
            $query = $query->with($with);
        }
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
     * @param string $with
     * @param bool $filter
     * @return mixed
     */

    public function list($request, $with = '')
    {
        $filed = $request->get('field',$this->repository->listFields);
        $query = $this->getOptions($request->all());
        $query = $this->order($query, $request->get('order'));
        $query = $this->group($query, $request->get('group'));
        if ($with) {
            $query = $this->with($query, $with, $request->all());
        }
        $limit = !empty($request->get('per_page')) ? $request->get('per_page') : $request->get('limit');
        switch ($request->get('data_type', 'list')) {
            case  'export':
                $query = $this->group($query, $request->get('group'));
                $result['data'] = $query->get($filed)->toArray();
                $data = $this->haddleList($result);
                Common::handleExportCsv($data['data'], $this->header, $request->get('date', $request->get('month')), $this->export_name);
                break;
            case  'total':
                $data = $query->select($filed)->first();
//                echo $query->toSql().PHP_EOL;
                break;
            case  'count':
                $data = $query->select($filed)->count();
//                echo $query->toSql().PHP_EOL;
                break;
            case  'list':
                $query = $this->group($query, $request->get('group'));
                $sql = $query->toSql();
                $list = $query->paginate($limit, $filed)->toArray();
                $data = $this->haddleList($list);
                $data['sql'] = $sql;
                $data['header'] = Common::handleHeader($this->header);
                break;
            case  'lists':
                $query = $this->group($query, $request->get('group'));
                $query = $query->select($filed);
                $data = $query->limit($limit)->get()->toArray();
                break;
            case  'first':
                $query = $this->group($query, $request->get('group'));
                $query = $query->select($filed);
                $data = $query->first()->toArray();
                break;
            case  'top':
                $query = $this->group($query, $request->get('group'));
                $query = $query->select($filed);
                $list = $query->limit($limit)->get()->toArray();
                $data = $this->haddleList($list);
                break;
            default:
                $data = [];

        }
        return $data;
    }

    /**
     * 设置 Repository
     * @param $repository
     */
    public function setRepository($repository)
    {
        $this->repository = $repository->getModel();
    }

    /**
     * 处理分页数据
     * @param $data
     * @return mixed
     */
    public function haddleList($data)
    {
        $data = $this->destroyPaging($data);
        return $data;
    }

    protected function destroyPaging($data)
    {
        unset($data['next_page_url']);
        unset($data['prev_page_url']);
        unset($data['path']);
        unset($data['links']);
        unset($data['first_page_url']);
        unset($data['last_page_url']);
        return $data;
    }

    /**
     * 查询参数验证
     * @param $data
     * @throws ValidationException
     */
    public function selectValidate($data)
    {
        $validator = Validator::make($data, $this->validateRule, $this->validateErroy);
        if ($validator->fails()) {
            throw new ValidationException ($validator->errors()->first());
        }
    }



}
