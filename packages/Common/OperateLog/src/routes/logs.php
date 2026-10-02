<?php
$router->middleware("auth.user")->group(function () use ($router) {
    $router->group(['prefix' => "operation-logs"], function () use ($router) {
        // 操作日志
        $router->get('/list', 'LogController@index');

    });
});

/**
 * 系统错误日志
 */
$router->group(['namespace' => '\Rap2hpoutre\LaravelLogViewer'], function () use ($router) {
    $router->get('logs', 'LogViewerController@index');
});


