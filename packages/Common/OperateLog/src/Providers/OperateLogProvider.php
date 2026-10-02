<?php

namespace Common\OperateLog\Providers;

use Illuminate\Support\ServiceProvider;
use Common\OperateLog\Repositories\LogRepositoryEloquent;

class OperateLogProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //路由
        $this->app->router->group(['namespace' => 'Common\OperateLog\Http\Controllers'], function ($router) {
            foreach (glob(__DIR__ . "/../routes/*.php") as $filename) {
                require $filename;
            }
        });
    }

    public function boot()
    {
        //注册
        $this->app->bind('OperateLog', '\Common\OperateLog\Repositories\LogRepositoryEloquent');

        $this->app->make(\Illuminate\Contracts\Http\Kernel::class)->pushMiddleware(\Common\OperateLog\Http\Middleware\OperateLogMiddleware::class);
    }
}
