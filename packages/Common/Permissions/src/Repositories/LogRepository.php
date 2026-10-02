<?php

namespace Common\Permissions\Repositories;

use Illuminate\Database\Eloquent\Model;
use Common\Permissions\Models\Authority;

use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Criteria\RequestCriteria;
use Zhuzhichao\IpLocationZh\Ip;
use \Illuminate\Support\Facades\Request as RequestFacade;
use Common\Permissions\Facades\LifeData;
use Common\Permissions\Models\Log;

use Prettus\Repository\Contracts\RepositoryInterface;
/**
 * Class UserRepository.
 *
 * @package namespace App\Repositories;
 */
class LogRepository extends BaseRepository implements RepositoryInterface
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

    protected $hidden = ['password','token','password_confirmation'];//隐藏参数
    protected $dispared = ['token',];//过滤太长的参数
    protected $filterRoute = [];//过滤不写日志的路由


    protected $log_id_key='_log_id';
    /**
     * 写入日志前置操作
     */
    public function writeBefore(){
        $route =  app()->request->path();
        if(in_array($route,$this->filterRoute)){
            return true;
        }
        //请求方式
        $method = strtolower(app('request')->method());
        if(!in_array(strtolower($method),config('app.log_exclude_method',[]))){
            $ip = RequestFacade::header('x-real-ip',RequestFacade::ip());
            $parameters = RequestFacade::all();
            collect($this->hidden)->map(function ($value)use(&$parameters){
                if(isset($parameters[$value])){
                    $parameters[$value] = preg_replace("/./","*",$parameters[$value]);
                }
            });
            collect($this->dispared)->map(function ($value)use(&$parameters){
                if(isset($parameters[$value])){
                   unset($parameters[$value]);
                }
            });
            $user=auth()->user()??LifeData::get('user');
//            echo $user->name;exit;
            if(!$user)return;

            $authority = Authority::where('route', $route)->first();
            $log = $this->model->create([
                'menu_id' => LifeData::get('menu_id'),
                // 'menu'=>LifeData::get('menu'),
                'menu' => $authority ? $authority->label : '',
                'user_id' => $user->id,
                'route' => $route,
                'user' => $user->name,
                'location' => collect(Ip::find($ip))->take(4)->filter()->unique()->implode(','),
                'ip' => $ip,
                'parameters' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
                'return' => ''
            ]);
            LifeData::set($this->log_id_key, $log['id']);
        }

    }
    /**
     * 写入日志后部分
     */
    public function writeAfter(){

    }
    /**
     * Boot up the repository, pushing criteria
     */
    public function boot()
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }

}
