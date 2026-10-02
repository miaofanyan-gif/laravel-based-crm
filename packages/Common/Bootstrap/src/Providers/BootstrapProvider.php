<?php
/**
 *  自动注册类，
 * 自动注册config 和 provider
 * provider目录必须要在 * /src/Providers/*.php
 */

namespace Common\Bootstrap\Providers;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Queue\Events\JobFailed;
use Queue;
use Log;

class BootstrapProvider extends ServiceProvider
{
    //默认注册跟模块名字相同的provider，如果需要额外注册的， 需要配置在这里。 key表示如果
    private $provides=[
       // 'Common\\Auth\\Providers\\AuthServiceProvider',

    ];
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //自动加载provider 仅自动加载的文件格式：Common\\包名\Providers\\包名Provider.php
        foreach (glob(__DIR__ . "/../../../*/src/Providers/*Provider.php") as $filename) {

            $filename = pathinfo($filename)['filename'];

            $model = Str::of($filename)->before('Provider');
            if ($model == 'Bootstrap') continue;
            $class = "Common\\" . $model . "\Providers\\" . $filename;
            if(!class_exists($class)) {
                continue;
            }
            $this->app->register($class);
        }
        //加载自定义provider
        foreach ($this->provides as $provide){
            $this->app->register($provide);

        }

    }

    /**
     * 启动应用服务
     *
     * @return void
     */
    public function boot()
    {

    }

}
