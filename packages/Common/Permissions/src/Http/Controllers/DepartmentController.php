<?php

namespace Common\Permissions\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Common\Permissions\Common\RepositoryException;
use Common\Permissions\Http\Request\DepartmentRequest;
use Common\Permissions\Repositories\DepartmentRepository;
use Common\Permissions\Repositories\DepartmentUserRepository;

class DepartmentController extends BaseController
{

    private $departmentRepository;
    private $departmentUserRepository;

    public function __construct(DepartmentRepository $departmentRepository, DepartmentUserRepository $departmentUserRepository)
    {
        $this->departmentRepository     = $departmentRepository;
        $this->departmentUserRepository = $departmentUserRepository;
        parent::__construct($departmentRepository);
    }


    /**
     * 获取部门列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function departmentList(Request $request)
    {
        try {
            return $this->ResponseSucess($this->departmentRepository->listDepartment($request->all()));
        } catch (RepositoryException $e) {
            return $this->ResponseFail($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 更新部门信息
     * @param DepartmentRequest $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Common\Permissions\Common\RepositoryException
     */
    public function updateDepart(DepartmentRequest $request)
    {
        $data = $request->only(['id', 'tag']);
        try {
            return $this->ResponseSucess($this->departmentRepository->updateDepart($data));
        } catch (RepositoryException $exception) {
            return $this->ResponseFail($exception->getCode(), $exception->getMessage());
        }
    }

    /**
     * 获取部门+用户
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function departmentTree(Request $request)
    {
        try {
            $params = $request->only(['flag']);
            $flag = !empty($params['flag']) ? filter_var($params['flag'], FILTER_VALIDATE_BOOLEAN) : true;
            return $this->ResponseSucess($this->departmentUserRepository->getDepartmentAndUsers(false, $flag));
        } catch (RepositoryException $e) {
            return $this->ResponseFail($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 创建、编辑部门信息
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleDepartment(Request $request)
    {
        $data = $request->only(['id', 'name', 'dep_pid', 'dep_id']);
        try {
            return $this->ResponseSucess($this->departmentRepository->handleDepartment($data));
        }catch (\Exception $e) {
            Log::channel('user_info')->warning("handleDepartment <UNK>:" . $e->getMessage().$e->getFile().$e->getLine());
            return $this->ResponseFail($e->getCode(), '添加部门数据异常');
        }
    }

    /**
     * 删除部门信息
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteDepartment(Request $request)
    {
        $data = $request->only(['id']);
        try {
            return $this->ResponseSucess($this->departmentRepository->deleteDepartment($data));
        }catch (\Exception $e) {
            Log::channel('user_info')->warning("handleDepartment <UNK>:" . $e->getMessage().$e->getFile().$e->getLine());
            return $this->ResponseFail($e->getCode(), '添加部门数据异常');
        }
    }

}
