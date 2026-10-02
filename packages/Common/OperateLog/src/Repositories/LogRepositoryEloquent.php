<?php

namespace Common\OperateLog\Repositories;


use Common\Libs\Common;
use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Criteria\RequestCriteria;
use Zhuzhichao\IpLocationZh\Ip;
use \Illuminate\Support\Facades\Request as RequestFacade;
use Common\Libs\Facades\LifeData;
use Common\OperateLog\Models\Log;
use Common\Permissions\Models\Menu;
use Illuminate\Support\Facades\Cache;

use Prettus\Repository\Contracts\RepositoryInterface;

/**
 * Class UserRepositoryEloquent.
 *
 * @package namespace App\Repositories;
 */
class LogRepositoryEloquent extends BaseRepository implements RepositoryInterface
{

    /**
     * @var Model
     */
    protected $model;


    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return Log::class;
    }

    protected $hidden = ['password', 'token', 'password_confirmation', "pwd"];//隐藏参数
    protected $dispared = ['token',];// 过滤太长的参数
    protected $filterRoute = ['operation-logs/list'];// 过滤不写日志的路由

    protected $filterRoutePrefix = []; // 过滤不写的路由前缀;

    protected $log_id_key = '_log_id';

    /**
     * 写入日志前置操作
     * @return bool|void
     */
    public function writeBefore()
    {
        $route = app()->request->path();

        foreach ($this->filterRoutePrefix as $filterRoutePrefix) {
            if (strpos($route, $filterRoutePrefix) !== false) {
                return true;
            }
        }

        if (in_array($route, $this->filterRoute)) {
            return true;
        }

        //请求方式
        $method = strtolower(app('request')->method());
        if (!in_array(strtolower($method), config('app.log_exclude_method', []))) {
            $ip = RequestFacade::header('x-real-ip', RequestFacade::ip());
            $parameters = RequestFacade::all();

            collect($this->hidden)->map(function ($value) use (&$parameters) {
                if (isset($parameters[$value])) {
                    $parameters[$value] = preg_replace("/./", "*", $parameters[$value]);
                }
            });

            collect($this->dispared)->map(function ($value) use (&$parameters) {
                if (isset($parameters[$value])) {
                    unset($parameters[$value]);
                }
            });

            $user = auth()->user() ?? LifeData::get('user');
            if ($user) {
                $userId = $user->id;
                $userName = $user->name;
            }

            if (!$user) return false;

            $menu = $this->getMenu($route);
            $log = $this->model->create([
                'menu_id'    => $menu ? $menu['id'] : '',
                'menu'       => $menu ? $menu['label'] : '',
                'user_id'    => $userId,
                'route'      => $route,
                'user'       => $userName,
                'location'   => collect(Ip::find($ip))->take(4)->filter()->unique()->implode(','),
                'ip'         => $ip,
                'parameters' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
                'return'     => ''
            ]);
            LifeData::set($this->log_id_key, $log);
        }
    }

    private function getMenu($route)
    {
        $cache_key = 'menu_server_route_cache';
        if(Cache::has($cache_key)){
            $result = Cache::get($cache_key);
        }else{
            $result = Menu::select(['id','label','server_route'])
                ->get()->toArray() ;
            $result = array_column($result, null,'server_route');
            Cache::put($cache_key, $result, 43200);
        }
        return !empty($result[$route])?collect($result[$route]):null;
    }

    /**
     * 写入日志后部分
     */
    public function writeAfter()
    {
        // LifeData::get($this->log_id_key);
    }

    /**
     * Boot up the repository, pushing criteria
     */
    public function boot()
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }

}
