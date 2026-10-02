<?php
use Illuminate\Support\Facades\Route;
use Common\Permissions\Http\Controllers\DepartmentController;

Route::prefix('permissions')->group(function () {

    // 部门管理
    Route::prefix('department')->middleware(['auth.user'])->group(function () {
        // 部门列表
        Route::get('/list', [DepartmentController::class,'departmentList']);
        //部门分类-树
        Route::get('/tree', [DepartmentController::class,'departmentTree']);

        // 添加、编辑部门信息
        Route::post('handle', [DepartmentController::class,'handleDepartment']);
        //删除部门信息
        Route::post('del', [DepartmentController::class,'deleteDepartment']);

        //更新部门数据
        Route::post('updateDepart', [DepartmentController::class,'updateDepart']);

    });

});