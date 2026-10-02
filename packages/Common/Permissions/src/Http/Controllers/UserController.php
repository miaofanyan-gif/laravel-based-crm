<?php

namespace Common\Permissions\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Permissions\src\Facades\Permissions;
use Common\Permissions\Facades\Users;
use Common\Permissions\Http\Request\UserRequest;
use Common\Permissions\Repositories\DepartmentRepository;
use Common\Permissions\Repositories\DepartmentUserRepository;
use Common\Permissions\Repositories\PermissionsRepository;
use Common\Permissions\Repositories\RoleRepository;
use Common\Permissions\Repositories\RoleUserRepository;
use Illuminate\Http\Request;
use Common\Permissions\Models\User;
use Common\Permissions\Common\RepositoryException;
use Common\Permissions\Repositories\UsersRepository;

class UserController extends BaseController
{
	private $departmentUserRepository;
	private $media_type;
	private $roleUserRepositoryEloquent;
    private $userRepository;
    private $permissionsRepository;
    private $roleRepository;
    private $departmentRepository;

    function __construct(
        DepartmentUserRepository $departmentUserRepository,
        RoleUserRepository $roleUserRepositoryEloquent,
        UsersRepository $userRepository,
        PermissionsRepository $permissionsRepository,
        RoleRepository  $roleRepository,
        DepartmentRepository $departmentRepository
    	)
    {
    	$this->departmentUserRepository = $departmentUserRepository;
    	$this->media_type = config('media.media_type');
    	$this->roleUserRepositoryEloquent = $roleUserRepositoryEloquent;
        $this->userRepository = $userRepository;
        $this->permissionsRepository = $permissionsRepository;
        $this->roleRepository = $roleRepository;
        $this->departmentRepository = $departmentRepository;
    	parent::__construct($departmentUserRepository);
    }


    /**
     * 用户列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function userList(Request $request)
    {
        try {
            $result = $this->userRepository->handleUserList($request->all());
            return $this->ResponseSucess($result);
        }catch (RepositoryException $exception){
            return $this->ResponseFail($exception->getCode(), $exception->getMessage());
        }
    }

    /**
     * 添加编辑用户
     * @param UserRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleUser(UserRequest $request)
    {
        $data = $request->only(['id', 'name', 'email', 'password', 'department_id', 'status', 'mobile', 'role_id', 'media_id','data_permission']);

        try {
            $info = $this->userRepository->handleUser($data);
            return $this->ResponseSucess();
        } catch (RepositoryException $exception) {
            Log::channel('user_info')->warning("添加、编辑用户失败：" . $exception->getMessage() . $exception->getFile() . $exception->getLine());
            return $this->ResponseFail($exception->getCode(), $exception->getMessage());
        } catch (\Exception $excep) {
            Log::channel('user_info')->warning("添加、编辑用户失败：" . $excep->getMessage() . $excep->getFile() . $excep->getLine());
//            return $this->ResponseFail($excep->getCode(), '操作异常');
            return $this->ResponseFail($excep->getCode(), $excep->getMessage() . $excep->getFile() . $excep->getLine());
        }
    }

    /**
     * 状态修改
     */
    public function statusUser(UserRequest $request)
    {
        //数据验证
        $data = $request->only(['user_id', 'status']);
        try {
            //判断数据是否存在
            $info = $this->userRepository->isExit($data['user_id']);
            if (auth()->user()->id == $data['user_id'])
                return $this->ResponseFail(21123, '对不起，您不能禁用自己的账户');
            //请求数据
            if ($this->userRepository->where(['id' => $data['user_id']])->update(['status' => (string)$data['status']]))
                return $this->ResponseSucess();
            else
                return $this->ResponseFail(20133, '用户状态修改失败');
        } catch (RepositoryException $exception) {
            return $this->ResponseFail(10041, '数据处理未知错误');
        }
    }



    /**
     * 获取用户权限
     */
    public function userAuthority(Request $request)
    {
        try {
            $data = $this->permissionsRepository->getRoleAuthority($request->input('system_number', 'RD0001'));
            return $this->ResponseSucess($data);
        } catch (RepositoryException $exception) {
            return $this->ResponseFail($exception->getCode(), $exception->getMessage());
        }
    }
    /**
     * 用户详情
     */
    public function userDetails(Request $request)
    {
        try {
            $params = $request->all();
            return $this->ResponseSucess(Users::userDetail($params['id'] ?? null));
        } catch (RepositoryException $exception) {
            return $this->ResponseFail($exception->getCode(), $exception->getMessage());
        }
    }
    public function index(Request $request)
    {
        $result = $this->list($request,'with');
        return $this->ResponseSucess($result);
    }

    public function haddleList($data)
    {
        $area_name = User::$area;
        foreach ($data['data'] as &$item){
            $media_name = null;
            if(!empty($item['media_id'])){
                $mediaIds = json_decode($item['media_id'], true);
                foreach ($mediaIds as $mediaId) {
                    $media_name .= $this->media_type[$mediaId] . ',';
                }
            }
            $item['area_name'] = $area_name[$item['area']]??null;
            $item['media_names'] = rtrim($media_name,',');
        }
        return parent::haddleList($data);
    }



    /**
     * 获取地区接口
     */
    public function getUserArea()
    {
        try {
            $result = [];
            foreach((User::$area) as $k=>$v){
                $result[] = ['value' => $k, 'label' => $v];
            }
            return $this->ResponseSucess($result);
        } catch (RepositoryException $exception) {
            return $this->ResponseFail($exception->getCode(), $exception->getMessage());
        }
    }

    /**
     * 按照区域和角色查询用户
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRoleUser(Request $request){
        try {
            $role_index_map = [
                'sale'           => ['sale_director', 'sale_manage', 'sale_head', 'sale_staff'],
                'optimize'       => ['optimize_director', 'optimize_manage', 'optimize_head', 'optimize_staff'],
                'media'          => ['media_director', 'media_manage', 'media_head', 'media_staff'],
                'sale_assistant' => ['sale_assistant']
            ];

            $role_type = $request->get('role_type', '');
            $user_name = $request->get('name', '');
            $data = $request->all();


            $result = [];
            foreach ($role_index_map as $index =>  $role_map){
                if(!empty($role_type) && $index != $role_type){
                    continue;
                }

                $roleResult = $this->roleRepository->select(['id','index'])->whereIn('index',$role_map)->pluck('id')->toArray();

                $result[$index] = $this->userRepository->getRoleUserList($roleResult,$user_name,$data);
            }


            return $this->ResponseSucess($result);
        } catch (RepositoryException|\Exception $exception) {
            Log::channel('user_info')->warning("根据角色获取用户信息失败：".$exception->getMessage().$exception->getFile().$exception->getLine());
            return $this->ResponseFail($exception->getCode(), $exception->getMessage());
        }
    }


    /**
     * 根据组长获取下属人员
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getGroupToOptimizes(Request $request){
        try {
            list($role_param,$search) = \JoyGc\Dashboard\Server\AccountRole::getAccountRole();

            $roleIds = $this->roleRepository->whereIn('index', ['sale', 'sale_director'])->pluck('id')->toArray();

            $sale_id = [];
            switch ($role_param['role_index']) {
                case 'area_director':
                    $sale_id = $role_param['polymerize']['sale_id'] ?? [];
                    break;
                case 'sale_director':
                    $sale_id = $role_param['sale_id'] ?? [];
                    break;
                case 'sale':
                    $sale_id = (array)$role_param['sale_id'];
                    break;
            }
            $result['sale'] = $this->userRepository->getSaleUserList($sale_id, $roleIds);
//echo $role_param['sale_group_id'];
            $all_optimize = $this->userRepository->userOptimizeCacheList();
            foreach ($all_optimize as $key=>&$item){
                switch ($role_param['role_index']) {
                    case 'area_director':
                        if($item['director_id'] != $role_param['polymerize']['manage_id'] || in_array($item['optimize_id'],\GuzzleHttp\json_decode($role_param['sale_ids']))){
                            unset($all_optimize[$key]);
                        }
                        break;
                    case 'edirector':
                        if($item['director_id'] != $role_param['manage_id'] || $item['status'] == -1){
                            unset($all_optimize[$key]);
                        }
                        break;
                    case 'group':
                        if($item['group_id'] != $role_param['group_id'] || $item['status'] == -1){
                            unset($all_optimize[$key]);
                        }
                        break;
                    case 'optimize':
                        if($item['optimize_id'] != $role_param['optimize_id'] || $item['status'] == -1){
                            unset($all_optimize[$key]);
                        }
                        break;
                    case 'super_admin':

                        break;
                    default:
                        unset($all_optimize[$key]);
                }
            }
            $result['optimize'] = array_values($all_optimize);
            return $this->ResponseSucess($result);
        } catch (RepositoryException $exception) {
            return $this->ResponseFail($exception->getCode(), $exception->getMessage());
        }
    }

    public function getUserIdToSubord()
    {
        $result = $this->userRepository->getUserIdToSubord();
        return $this->ResponseSucess($result);
    }



    /**
     * 获取部门+用户
     * @return JsonResponse
     */
    public function getUserList()
    {
        //加入缓存
        $key = 'authorize_user_list';
        if (Redis::exists($key)) {
            $result = Redis::get($key);
            return $this->ResponseSucess(json_decode($result, true));
        }
        try {
            $userList = $this->departmentUserRepository->getDepartmentAndUsers(true);
            Redis::setex($key, 86400, json_encode($userList));
            return $this->ResponseSucess($userList);
        } catch (\Common\Exceptions\RepositoryException $exception) {
            return $this->ResponseFail($exception->getCode(), $exception->getMessage());
        }
    }

    /**
     * 修改密码
     */
    public function upPassword(UserRequest $request)
    {
        $data = $request->only(['id', 'password']);
        try {
            //请求数据
            $info = $this->userRepository->setPassword($data);
            return $this->ResponseSucess();
        } catch (\Common\Permissions\Common\RepositoryException $exception) {
            return $this->ResponseFail($exception->getCode(), $exception->getMessage());
        }
    }

    public function tenantUserList(Request $request)
    {
        $data = $request->only(['ename', 'per_page', 'role_id', 'group_id', 'department_id', 'company_id', 'status']);
        $query = User::select(['id', 'name', 'email', 'status']);
        if(!empty($data['status'])){
            $query = $query->where('status', $data['status']);
        }
        $result = $query->orderBy('id', 'desc')->paginate($data['per_page']);
        return $this->ResponseSucess($result);
    }

    /**
     * 用户详情
     * @param UserRequest $request
     */
    public function getConfigs(Request $request)
    {
        try {
            $data = $request->all();
            $config = $this->userRepository->getConfigs($data);
            if(!empty($data['is_data_permissions'])){
                return $this->ResponseSucess($config);
            }

            $config['systems'] = [];
            $config['roles'] = $this->roleRepository->getAllRole();
            $config['departments'] = $this->departmentRepository->allDepart($data);
            return $this->ResponseSucess($config);
        } catch (RepositoryException $exception) {
            return $this->ResponseFail($exception->getCode(), $exception->getMessage());
        }
    }


    /**
     * 发送邮件
     * @param UserRequest $request
     * @return JsonResponse
     */
    public function sendMail(UserRequest $request): JsonResponse
    {
        $data = $request->all();
        try {
            //请求数据
            $userData = $this->userRepository->sendMail($data['email']);
            return $this->ResponseSucess($userData);
        } catch (RepositoryException $exception) {
            return $this->ResponseFail($exception->getCode(), $exception->getMessage());
        }
    }

    /**
     * 重设密码
     * @param UserRequest $request
     * @return JsonResponse
     */
    public function resetPassword(UserRequest $request): JsonResponse
    {
        $data = $request->all();
        try {
            //请求数据
            $userData = $this->userRepository->resetPassword($data);
            return $this->ResponseSucess($userData);
        } catch (RepositoryException $exception) {
            return $this->ResponseFail($exception->getCode(), $exception->getMessage());
        }
    }


}
