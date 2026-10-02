<?php

namespace Common\Permissions\Providers;

use Illuminate\Support\ServiceProvider;
use Common\Permissions\Http\Middleware\CheckAuthority;
use Common\Permissions\Server\DingTalkRobot;

class PermissionsProvider extends ServiceProvider
{
    public function boot()
    {
        // 发布配置
        $this->publishes([__DIR__ . '/../Config/permissions.php' => config_path('permissions.php')]);

    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //路由
        $this->app->router->group(['namespace' => 'Common\Permissions\Http\Controllers'], function ($router) {
            foreach (glob(__DIR__ . "/../routes/*.php") as $filename) {
                require $filename;
            }
        });
        //注册门面模式
        $this->app->bind('Permissions', "Common\Permissions\Repositories\PermissionsRepository");
        $this->app->bind('Department', "Common\Permissions\Repositories\DepartmentRepository");
        $this->app->bind('DepartmentUser', "Common\Permissions\Repositories\DepartmentUserRepository");
        $this->app->bind('DingTalkRobot', function ($app, $robotKey) {
            return new DingTalkRobot($robotKey);
        });
        $this->app->bind('LifeData', "Common\Permissions\Repositories\LifeDataRepository");
        $this->app->bind('Users', "Common\Permissions\Repositories\UsersRepository");//用户
        //绑定门面类的别名
        class_alias("Common\Permissions\Facades\DingTalkRobot", "DingTalkRobot");

        // 加载配置文件
        /**
         * 引如config 文件
         */
        $this->mergeConfigFrom(__DIR__.'/../Config/media.php','media');
        $this->mergeConfigFrom(__DIR__.'/../Config/permissions.php','permissions');
        $this->mergeConfigFrom(__DIR__.'/../Config/logging.php','logging.channels');//合并日志
        // 注册中间件
        $this->app['router']->aliasMiddleware('auth.login', CheckAuthority::class);
    }
}
