<?php

namespace JoyCrm\OaApproval\Providers;

use Illuminate\Support\ServiceProvider;
use JoyCrm\OaApproval\Server\ApprovalEngine;

class OaApprovalProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // 路由
        $this->app->router->group(['namespace' => 'JoyCrm\\OaApproval\\Http\\Controllers'], function ($router) {
            foreach (glob(__DIR__ . "/../routes/*.php") as $filename) {
                require $filename;
            }
        });

        // 独立日志通道（backend-conventions.md §11：包自己的 Config/logging.php，不改根 config/logging.php）
        $this->mergeConfigFrom(__DIR__ . '/../Config/logging.php', 'logging.channels');

        // 审批引擎单例，供 Facades\OaApproval 和跨包 use JoyCrm\OaApproval\Server\ApprovalEngine 使用
        $this->app->singleton('JoyCrmOaApproval', function ($app) {
            return $app->make(ApprovalEngine::class);
        });
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
