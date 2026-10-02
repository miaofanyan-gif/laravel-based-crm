<?php
use Illuminate\Support\Facades\Route;
Route::prefix('permissions')->group(function () {
    //SSO登录
    Route::prefix('ucenter')->group(function () {
        //SSO LOGIN
        Route::any('/sso/login', 'LoginController@ssoLogin');
        Route::any('/checkToken', 'LoginController@checkToken');
        Route::any('/sso/logout', 'LoginController@ssoLogout');
        Route::any('/sso/jwt_black_clear', 'LoginController@flushBlacklist');
        Route::post('/wechat/login', 'LoginController@getCodeLogin');
    });


    Route::prefix('user')->middleware(['auth.login'])->group(function () {
        //获取部门+用户列表
        Route::get('/userList', 'UserController@getUserList');
        //查询人员
        Route::get('role_user', 'UserController@getRoleUser');
    });

    // 用户管理
    Route::prefix('user')->middleware(['auth.user'])->group(function () {
        // 用户详情
        Route::get('/details', 'UserController@userDetails');
        // 用户权限
        Route::get('/authority', 'UserController@userAuthority');
        // 用户列表
        Route::get('/list', 'UserController@userList');

        // 修改用户状态
        Route::patch('/status', 'UserController@statusUser');

        // 添加用户给
        Route::post("handle", "UserController@handleUser");


    });

    // 验证登录就可以
    Route::prefix('user')->middleware(['auth.login'])->group(function () {
        Route::get('group_to_optimize', 'UserController@getGroupToOptimizes');
        Route::get('user_sub', 'UserController@getUserIdToSubord');//查询当前人所属人员结构
        Route::get('configs', 'UserController@getConfigs');

        //部门管理
        Route::get('/departmentList', 'DepartmentController@departmentList');
        // 修改用户密码
        Route::patch('/password', 'UserController@upPassword');
    });

});

$router->group([ 'middleware'=>"auth.login",'prefix'=>"tenant"], function () use ($router) {
    // 用户列表
    $router->get('/list', 'UserController@tenantUserList');
});