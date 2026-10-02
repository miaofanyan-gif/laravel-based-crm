<?php

// 审批流配置（后台配置端，§12.1）
$router->group(['prefix' => 'oa/flow-template', 'middleware' => 'auth.user'], function () use ($router) {
    $router->get('list', 'FlowTemplateController@index');
    $router->get('detail', 'FlowTemplateController@detail');
    $router->post('add', 'FlowTemplateController@add');
    $router->post('edit', 'FlowTemplateController@edit');
    $router->patch('status', 'FlowTemplateController@status');
    $router->delete('del', 'FlowTemplateController@del');
});

// 审批任务（用户操作端，§12.2）
$router->group(['prefix' => 'oa/approval-task', 'middleware' => 'auth.user'], function () use ($router) {
    $router->get('pending', 'ApprovalTaskController@pending');
    $router->get('mine', 'ApprovalTaskController@mine');
    $router->get('all', 'ApprovalTaskController@all');
    $router->get('detail', 'ApprovalTaskController@detail');
    $router->post('submit', 'ApprovalTaskController@submit');
    $router->post('approve', 'ApprovalTaskController@approve');
    $router->post('reject', 'ApprovalTaskController@reject');
    $router->post('return', 'ApprovalTaskController@return');
    $router->post('complete', 'ApprovalTaskController@complete');
    $router->post('withdraw', 'ApprovalTaskController@withdraw');
});
