<?php
use Illuminate\Support\Facades\Route;
Route::prefix('permissions')->group(function () {
    Route::group(['prefix'=>"menu",'middleware' => 'auth.user'], function () {
        // 菜单列表
        Route::get('/list', 'MenuController@menuList');

        // 新增菜单
        Route::post('/create', 'MenuController@createMenu');

        // 修改菜单
        Route::patch('/update', 'MenuController@upMenu');

        // 删除菜单
        Route::delete('/delete', 'MenuController@delMenu');
    });
});

