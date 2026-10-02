<?php
use Illuminate\Support\Facades\Route;
Route::prefix('permissions')->group(function () {
    Route::group(['prefix'=>"role",'middleware' => 'auth.user'], function () {
        // 角色列表
        Route::get('/list', 'RoleController@getList');

        Route::get('/all', 'RoleController@getAllRoles');

        // 角色详情
        Route::get('/details', 'RoleController@roleDetails');

        // 新增角色
        Route::post('/create', 'RoleController@createRole');

        // 修改角色
        Route::post('/update', 'RoleController@upRole');
        Route::patch('/update', 'RoleController@upRole');

        // 修改角色状态
        Route::patch('/state', 'RoleController@stateRole');

        // 删除角色
        Route::delete('/delete', 'RoleController@delRole');
        
        //设置权限
        Route::post('/set/permission', 'RoleController@setPermission');
        // 设置数据权限
        Route::post('/set/data/permission', 'RoleController@setDataPermission');
    });
});

