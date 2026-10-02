<?php

namespace Common\Libs\Providers;

use Illuminate\Support\ServiceProvider;

class LibsProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

        //注册门面模式
        $this->app->bind('life_data',"Common\Libs\LifeDataRepository");
        //绑定门面类的别名
        class_alias("Common\Libs\Facades\LifeData","LifeData");

    }
}
