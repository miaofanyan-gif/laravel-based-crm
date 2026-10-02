<?php

namespace Common\Permissions\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Common\Libs\Common;
use Common\Permissions\Http\Request\RoleRequest;
use Common\Permissions\Models\Role;
use Common\Permissions\Common\RepositoryException;
use Common\Permissions\Repositories\RoleRepository;

class RoleController extends BaseController
{
    private $request;
    private $roleRepository;

    function __construct(RoleRepository $roleRepository)
    {
        $this->roleRepository = $roleRepository;
        parent::__construct($roleRepository);
    }

    /**
     * 角色列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getList(Request $request)
    {
        try {
            $data = $this->roleRepository->getList($request);
            return $this->ResponseSucess($data);
        } catch (RepositoryException $exception) {
            return $this->ResponseFail($exception->getCode(), $exception->getMessage());
        }
    }


    /**
     * 角色详情
     * @param RoleRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function roleDetails(RoleRequest $request)
    {
        $data = $request->only(['id', 'system_number']);
        try {
            $data = $this->roleRepository->detailedInformation($data);
            return $this->ResponseSucess($data);
        } catch (RepositoryException $exception) {
            return $this->ResponseFail($exception->getCode(), $exception->getMessage());
        }
    }

    /**
     * @param RoleRequest $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \Common\Exceptions\RepositoryException
     */
    public function createRole(RoleRequest $request)
    {
        $data = $request->only(['name', 'desc', 'parent_id']);
        try {
            if ($id = $this->roleRepository->addRole($data))
                return $this->ResponseSucess(['id' => $id]);
        } catch (RepositoryException $exception) {
            return $this->ResponseFail($exception->getCode(), $exception->getMessage());
        }
    }

    /**
     * 修改角色
     */
    public function upRole(RoleRequest $request)
    {
        $data = $request->only(['id', 'name', 'desc', 'parent_id']);
        try {
            if ($this->roleRepository->updateRole($data))
                return $this->ResponseSucess();
        } catch (RepositoryException $exception) {
            return $this->ResponseFail($exception->getCode(), $exception->getMessage());
        }
    }

    /**
     * 修改角色状态
     */
    public function stateRole(RoleRequest $request)
    {
        $data = $request->only(['id', 'state']);
        try {
            //查找数据是否存在
            $this->isExist($data['id']);
            //进行修改
            if (Role::where(['id' => $data['id']])->update(['state' => $data['state']]))
                return $this->ResponseSucess();
            else
                return $this->ResponseFail(21258, '角色状态修改失败');
        } catch (RepositoryException $exception) {
            return $this->ResponseFail($exception->getCode(), $exception->getMessage());
        }
    }

    /**
     * 删除角色
     * @param RoleRequest $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \Common\Exceptions\RepositoryException
     */
    public function delRole(RoleRequest $request)
    {
        $data = $request->only(['id']);
        try {
            if ($this->roleRepository->deleteRole($data))
                return $this->ResponseSucess();
        } catch (RepositoryException $exception) {
            return $this->ResponseFail($exception->getCode(), $exception->getMessage());
        }
    }

    /**
     * 设置权限
     * @param RoleRequest $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \Common\Exceptions\RepositoryException
     */
    public function setPermission(RoleRequest $request)
    {
        $data = $request->only(['id', 'permissionData', 'system_number', 'authorize_menu_ids']);
        try {
            if ($data['system_number'] == 'RD0001') {
                if (empty($data['permissionData'])) {
                    throw new RepositoryException('缺少权限数据~', 10001);
                }
                $success = $this->roleRepository->setPermissionNew($data);
            } else {
                if (empty($data['authorize_menu_ids'])) {
                    throw new RepositoryException('缺少权限数据22222', 10001);
                }
                $success = $this->roleRepository->setPermission($data);
            }
            return $success ? $this->ResponseSucess() : $this->ResponseFail(500, '保存失败');
        } catch (RepositoryException $exception) {
            return $this->ResponseFail($exception->getCode(), $exception->getMessage());
        }
    }

    /**
     * 设置数据权限
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function setDataPermission(RoleRequest $request)
    {
        $data = $request->only(['id', 'index', 'media_ids', 'data_permission']);
        try {
            if ($this->roleRepository->setDataPermission($data))
                return $this->ResponseSucess();
        } catch (RepositoryException $exception) {
            return $this->ResponseFail($exception->getCode(), $exception->getMessage());
        }
    }

    /**
     * 获取所有角色
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllRoles()
    {
        try {

            return $this->ResponseSucess($this->roleRepository->getAllRole());
        } catch (RepositoryException $exception) {
            return $this->ResponseFail($exception->getCode(), $exception->getMessage());
        }
    }
}
