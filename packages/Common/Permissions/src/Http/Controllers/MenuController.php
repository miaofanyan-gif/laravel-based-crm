<?php

namespace Common\Permissions\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Common\Bootstrap\BaseController;
use Illuminate\Http\Request;
use Common\Bootstrap\Traits\ResponseTrait;
use Common\Permissions\Models\Menu;
use Illuminate\Support\Facades\Validator;
use Common\Permissions\Http\Request\MenuRequest;
use Common\Exceptions\RepositoryException;
use Common\Permissions\Repositories\MenuRepositoryEloquent;

class MenuController extends BaseController
{

	private $menuRepository;

    function __construct(MenuRepositoryEloquent $menuRepository)
    {
    	$this->menuRepository = $menuRepository;
        parent::__construct($menuRepository);
    }

    /**
     * 菜单列表
     */
    public function menuList(Request $request)
    {
        try {
            $data = $this->menuRepository->listMenu($request->all());
            return $this->ResponseSucess($data);
        } catch (RepositoryException $exception) {
            return $this->ResponseFail(10041, '数据处理未知错误');
        }
    }


    /**
     * 创建菜单
     */
    public function createMenu(MenuRequest $request)
    {
        //数据验证
        $data = $request->only(['pid', 'label', 'ico', 'index', 'sort', 'state','system_num', 'system_number']);
        try {
            if($this->menuRepository->createMenu($data))
                return $this->ResponseSucess([]);
            else
                return $this->ResponseFail(21200, '菜单添加失败');
        } catch (RepositoryException $exception) {
            Log::channel('user_info')->warning("创建菜单异常：". $exception->getMessage().$exception->getFile().$exception->getLine());
            return $this->ResponseFail(10041, '数据处理未知错误');
        }
    }

    /**
     * 修改菜单
     */
    public function upMenu(MenuRequest $request)
    {
        //数据验证
        $data = $request->only(['id', 'pid', 'label', 'ico', 'index', 'sort', 'state']);
        try {
            if ($this->menuRepository->menuProcessing($data))
                return $this->ResponseSucess();
            else
                return $this->ResponseFail(21201, '菜单修改失败');
        } catch (RepositoryException $exception) {
            return $this->ResponseFail($exception->getCode(), $exception->getMessage());
        }
    }

    /**
     * 删除菜单
     */
    public function delMenu(MenuRequest $request)
    {
        //数据验证
        $data = $request->only(['id']);
        try {
            if ($this->menuRepository->deleteMenu($data))
                return $this->ResponseSucess();
            else
                return $this->ResponseFail(21202, '菜单删除失败');
        } catch (RepositoryException $exception) {
            return $this->ResponseFail($exception->getCode(), $exception->getMessage());
        }
    }
}
