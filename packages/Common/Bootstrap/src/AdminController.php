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

class AdminController extends Controller
{

    use ResponseTrait;

    public $repository;
    public $model;
    public $request;
    public $header=[];
    public $validateRule=[];
    public $validateErroy=[];
    public $export_name='export';

    public function __construct($repository)
    {
        $this->repository = $repository;
        $this->model = $repository->getModel();
    }

    /**
     * 列表页面
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
//        $data = $this->request->all();
//        $this->paramValidate($data);
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
        $query = $this->model;
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
        $order = $order ? explode('|', $order) : $this->model->defaultOrder;
        //排序
        !empty($order) and collect($order)->each(function ($item, $key) use (&$query) {
            $order = explode(',', $item);
            if(isset($order[1]) && in_array($order[1], ['asc', 'desc','ASC','DESC'])){
                $type = $order[1] == 'asc' ? 'asc' : 'desc';
                $item and $query = $query->orderBy($order[0], $type);
            }else{
                $item and $query = $query->orderByRaw($item);
            }
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
//        Common::printR(method_exists($this->model, $withName));
        $with = $this->model->$withName ?  : (method_exists($this->model, $withName) ? $this->model->$withName($data) : []);
        if (!empty($with)) {//查看是不是  public
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
        $group = $group ? $group : ($this->model->defaultGroup??[]);
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
        $filed = $request->get('field',$this->model->listFields);
        $query = $this->getOptions($request->all());
        $query = $this->order($query, $request->get('order'));
        $query = $this->group($query, $request->get('group'));
        if ($with) {
            $query = $this->with($query, $with, $request->all());
        }
        $limit = !empty($request->get('per_page')) ? $request->get('per_page') : $request->get('limit');
//        Common::printR($request->all());
//        Common::printR($request->input('data_type'));die;
        switch ($request->get('data_type', 'list')) {
            case  'export':
                $query = $this->group($query, $request->get('group'));
                $result['data'] = $query->get($filed)->toArray();
                $data = $this->handleList($result);
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
                $list = $query->paginate($limit, $filed)->toArray();
                $data = $this->handleList($list);
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
                $data = $query->first();
                $data = $data?$data->toArray():[];
                break;
            case  'top':
                $query = $this->group($query, $request->get('group'));
                $query = $query->select($filed);
                $list = $query->limit($limit)->get()->toArray();
                $data = $this->handleList($list);
                break;
            case  'pluck':
                $query = $this->group($query, $request->get('group'));
                $data = $query->pluck($filed)->toArray();
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
        $this->repository = $repository;
        $this->model = $repository->getModel();
    }

    /**
     * 处理分页数据
     * @param $data
     * @return mixed
     */
    public function handleList($data)
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
     * 参数验证
     * @param $data
     * @throws ValidationException
     */
    public function paramValidate($data)
    {
        $validator = Validator::make($data, $this->validateRule, $this->validateErroy);
        if ($validator->fails()) {
            throw new ValidationException ($validator->errors()->first());
        }
    }

    /**
     * +----------------------------------------------------------
     * 创建操作
     * +----------------------------------------------------------
     */
    public function add(Request $request)
    {
        $params = $request->all();
        $this->request->createFrom($request);//验证数据
        if (method_exists($this->repository, 'before_add')) {
            $params = $this->repository->before_add($params);
        }
        $result = $this->repository->create($params);
        if ($result !== false) { //保存成功
            // 使用回调接口
            if (method_exists($this->repository, 'after_add')) {
                $this->repository->after_add($result);
            }
            return $this->ResponseSucess($result);
        } else {
            //失败提示
            return $this->ResponseFail(5000,'添加失败');
        }
    }

    /**
     * +----------------------------------------------------------
     * 编辑操作
     * +----------------------------------------------------------
     */
    public function edit(Request $request)
    {
        $params = $request->all();
        $this->request->createFrom($request);//验证数据
        $pk = $this->model->getKeyName();
        $id = $request->get('id',$request->get($pk));
        if (method_exists($this->repository, 'before_edit')) {
            $params = $this->repository->before_edit($params);
        }
        $result = $this->repository->where($pk,$id)->update($params);
        if ($result !== false) { //保存成功
            // 使用回调接口
            if (method_exists($this->repository, 'after_edit')) {
                $this->repository->after_edit($id);
            }
            return $this->ResponseSucess($result);
        } else {
            //失败提示
            return $this->ResponseFail(5000,'编辑失败');
        }
    }

    /**
     * +----------------------------------------------------------
     * 默认禁用操作
     * +----------------------------------------------------------
     */
    public function forbid(Request $request)
    {
        $params = $request->all();
        $this->request->createFrom($request);//验证数据
        $pk = $this->model->getKeyName();
        $ids = is_array($request->get($pk))?$request->get($pk):(array)$request->get($pk);
        if (method_exists($this->repository, 'before_forbid')) {
            $param = $this->repository->before_forbid($ids);
            $request->merge($param);
        }
        $result = $this->repository->whereIn($pk,$ids)->update(['status'=>$params['status']]);
        if ($result !== false) {
            // 使用回调接口
            if (method_exists($this->repository, 'after_forbid')) {
                $this->repository->after_forbid($ids);
            }
            return $this->ResponseSucess($result,'状态禁用成功~');
        } else {
            //失败提示
            return $this->ResponseFail(5000,'状态禁用失败!');
        }
    }

    /**
     * +----------------------------------------------------------
     * 删除操作
     * +----------------------------------------------------------
     */
    public function delete(Request $request)
    {
        $params = $request->all();
        $this->request->createFrom($request);//验证数据
        $pk = $this->model->getKeyName();
        $ids = is_array($params[$pk])?$params[$pk]:(array)$params[$pk];
        if (method_exists($this->repository, 'before_delete')) {
            $this->repository->before_delete($ids);
        }
        $result = $this->repository->whereIn($pk,$ids)->delete();
        if ($result !== false) {
            // 使用回调接口
            if (method_exists($this->repository, 'after_delete')) {
                $this->repository->after_delete($ids);
            }
            return $this->ResponseSucess($result,'删除成功~');
        } else {
            //失败提示
            return $this->ResponseFail(5000,'删除失败!');
        }
    }

    /**
     * +----------------------------------------------------------
     * 排序操作
     * +----------------------------------------------------------
     */
    public function sort(Request $request) {
        $params = $request->all();
        $this->request->createFrom($request);//验证数据
        $pk = $this->model->getKeyName();
        $ids = is_array($params[$pk])?$params[$pk]:(array)$params[$pk];
        $result = $this->repository->whereIn($pk,$ids)->update(['sorting'=>$params['sorting']??0]);
        if ($result !== false) {
            return $this->ResponseSucess($result,'排序设置成功~');
        } else {
            return $this->ResponseFail(5000,'排序设置失败!');
        }
    }

}
