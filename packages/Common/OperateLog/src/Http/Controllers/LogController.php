<?php

namespace Common\OperateLog\Http\Controllers;

use Illuminate\Http\Request;
use Common\OperateLog\Repositories\LogRepositoryEloquent;
use Common\Bootstrap\Traits\ResponseTrait;
use Common\Bootstrap\BaseController;

class LogController extends BaseController
{

    use ResponseTrait;

    public $repository;

    public function __construct(LogRepositoryEloquent $repository)
    {
        parent::__construct($repository);
    }


    /**
     * 操作日志列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $userInfo = auth()->user();

        if (1 != $userInfo->role_id && !isset($request['user_id'])) {
            $request['user_id'] = $userInfo->id;
        }

        $result = $this->list($request);

        return $this->ResponseSucess($result);
    }
}
