<?php

namespace Common\Permissions\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\View;
use Common\Permissions\Common\Common;
use Common\Permissions\Facades\Users;
use Common\Permissions\Http\Request\SigninRequest;
use Common\Permissions\Repositories\RoleRepository;
use Common\Permissions\Common\RepositoryException;
use Common\Permissions\Repositories\UsersRepository;
use JoyMedia\Wechat\Facades\WechatOauth;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class LoginController extends BaseController
{

    /**
     * @var App
     */
    protected $UserRepository;
    protected $RoleRepository;
    public $jtiKeyPrefix;

    public function __construct(UsersRepository $UserRepository, RoleRepository $RoleRepository)
    {
        $this->UserRepository = $UserRepository;
        $this->RoleRepository = $RoleRepository;
        $this->jtiKeyPrefix   = config('permissions.jtiKeyPrefix');
    }

    /**检查用户登录信息
     * @param SigninRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkToken(Request $request)
    {
        try {
            $params = $request->all();
            /*$ssoToken = $request->header('authorization', '');
            if (empty($ssoToken)) {
                return $this->ResponseFail(10002, '【1】token is empty');
            }*/
            if (empty($params['unionId']) || empty($params['userId'])) {
                return $this->ResponseFail(10002, '请求参数异常');
            }
            //解析tokenId
            $key           = config('app.key');
            $method        = config('app.cipher');
            $encrypted     = base64_decode($params['unionId']);
            $ivLength      = openssl_cipher_iv_length($method);
            $iv            = substr($encrypted, 0, $ivLength); // 提取 IV
            $encryptedData = substr($encrypted, $ivLength);    // 提取加密数据
            $decrypted     = openssl_decrypt($encryptedData, $method, $key, 0, $iv);

            $jti_token_key = $this->jtiKeyPrefix . ":{$params['userId']}:" . $decrypted;
            if (!Redis::connection('permission_cache')->exists($jti_token_key)) {
                return $this->ResponseFail(10003, '操作异常,请重试');
            }
            $redis_token = Redis::connection('permission_cache')->get($jti_token_key);
            $request->headers->set('Authorization', 'Bearer ' . $redis_token);

            $checkToken = Users::checkToken();
            if ($checkToken == false) {
                return $this->ResponseFail(10002, '【3】SSO登录验证失败,请使用账号密码登录');
            }
            $user  = auth()->user();
//            $token = JWTAuth::fromUser($user);
            //获取登录用户数据
            $data['user']  = Users::userDetail($user->id);
            $data['unionId'] = $params['unionId'];
            $data['tokenId'] = $decrypted;
            $data['token']  = $redis_token;
            $system_number = empty($params['system_number']) ? 'RD0001' : $params['system_number'];
            $data['route'] = $this->RoleRepository->getFirstLoginRoute($system_number);
            return $this->ResponseSucess($data);
        } catch (RepositoryException $exception) {
            return $this->ResponseFail($exception->getCode(), $exception->getMessage());
        }
    }

    /**
     * 登录
     * @param SigninRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ssoLogin(SigninRequest $request)
    {
        try {
            $params      = $request->all();
            $credentials = $request->only('email', 'password');
            $result      = $this->UserRepository->token($credentials);//token值（这个是和User模型对应的）
            if (empty($result)) {
                return $this->ResponseFail(10001, '登录验证失败');
            }
            $token   = $result["token"] ?? "";
            $tokenId = $result["tokenId"] ?? "";
            if ($token == false) {
                return $this->ResponseFail(10002, '企业邮箱或密码不正确');
            }
            $user = auth()->user();
            if ($user->status == 0) {
                return $this->ResponseFail(20102, '账户已停用');
            }
            if ($user->status == 2) {
                return $this->ResponseFail(20101, '账户未激活');
            }
            if ($user->status == -1) {
                return $this->ResponseFail(20101, '账户已停用');
            }
            if ($user->trashed()) {
                return $this->ResponseFail(20101, '账户已注销');
            }
            if($user->is_authorize == 0){
                return $this->ResponseFail(20101, '请联系上级领导开通权限');
            }

            if (empty(collect($user->role)->toArray()) && $user->role_id != 1) {
                return $this->ResponseFail(20102, '请联系管理员分配权限');
            }

            //获取登录用户数据
            $data['user']  = $this->UserRepository->userDetail($user->id, true);
            $data['token'] = $token;

            //对称加密token
            $key             = config('app.key');
            $method          = config('app.cipher');
            $ivLength        = openssl_cipher_iv_length($method);
            $iv              = random_bytes($ivLength); // 生成随机 IV
            $encrypted       = openssl_encrypt($tokenId, $method, $key, 0, $iv);
            $encrypted       = base64_encode($iv . $encrypted); // 将 IV 和加密数据拼接
            $data['unionId'] = $encrypted;

            // 系统标识
            $system_number = empty($params['system_number']) ? 'RD0001' : $params['system_number'];
            $data['route'] = $this->RoleRepository->getFirstLoginRoute($system_number);
            $this->UserRepository->loginSuccess($user);//登录成功
            $data['tokenId'] = $tokenId;
            $data['valid_start_date'] = "2025-01-01";
            $data['valid_end_date'] = "2099-01-01";
            $data['corporation_name'] = "金晨";
            return $this->ResponseSucess($data);
        } catch (RepositoryException $exception) {
            return $this->ResponseFail($exception->getCode(), $exception->getMessage());
        }
    }

    /**
     * 退出登录
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ssoLogout(Request $request)
    {
        $out_type = $request->input("out_type", "");
        $ssoToken = $request->bearerToken();
        $token    = $ssoToken ?: JWTAuth::getToken();
        Log::channel('user_info')->info("令牌退出使用token", ['error' => '令牌退出使用token', 'message' => $token]);
        try {
            JWTAuth::setToken($token);
            $payload = JWTAuth::getPayload($token)->toArray();
            Log::channel('user_info')->info("令牌退出使用jti", ['error' => 'jti', 'message' => $payload]);
            // 从载荷中获取用户 ID
            $userId = $payload['sub'] ?? null;
            if (!$userId) {
                return response()->json(['error' => 'User ID not found in token'], 400);
            }
            $tokenId = $payload['jti'] ?? null; // 从数组中获取jti
            // 从Redis中删除Token
            $jti_token_key = "user:jti:{$userId}:";
            if ($out_type === "all") {
                $jti_token_keys = $jti_token_key . "*";

                // 获取所有与此用户相关的 JTI
                $jtis = Redis::connection('permission_cache')->keys($jti_token_keys);
                foreach ($jtis as $key) {
                    Redis::connection('permission_cache')->del($key);
                }
            } else {
                //清空单个
                Redis::connection('permission_cache')->del($jti_token_key . $tokenId);
            }
            //删除用户缓存数据
            Redis::connection('permission_cache')->hdel(config('permissions.cache_user_detail_key'), $userId);

            //销毁token
            JWTAuth::invalidate($token);
            return $this->ResponseSucess(true);
        } catch (JWTException $e) {
            // 如果捕获到异常，则说明缺少令牌或者令牌无效
            Log::channel('user_info')->info("令牌无效", ['error' => 'could_not_create_token', 'message' => $e->getMessage()]);
            return $this->ResponseFail(403, $e->getMessage());
        }
    }

    /**
     * 清空jwt黑名单
     * @return \Illuminate\Http\JsonResponse
     */
    public function flushBlacklist()
    {
        // dd(JWTAuth::getBlacklist());
        // 清空整个黑名单
        JWTAuth::getBlacklist()->clear();

        return response()->json(['message' => 'Blacklist flushed.'], 200);
    }


    /**
     * 根据code获取openid并判断用户是否报名体验课
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCodeLogin(Request $request)
    {
        $data = $request->all();
        Log::channel('wechat')->info('接受参数', [$data]);
        try{
            $code = $request->get('code');
            if(empty($code)){
                return View::make('auth.error', ['message'=>'非法操作','wait'=>3,'url'=>'javascript:;','type'=>'关闭']);
            }
            $state = $request->get('state');
            $wechatUserInfo= WechatOauth::getCodeToUserInfo($code);
            Log::channel('wechat')->info('用户信息: ' ,[$wechatUserInfo]);
            if (!isset($wechatUserInfo['raw']['openid'])){
                return View::make('auth.error', ['message'=>'授权信息不存在','wait'=>3,'url'=>'javascript:;']);
            }
            $user_info = $this->UserRepository->where('openid', $wechatUserInfo['raw']['openid'])->first();
            if(!$user_info){
                return $this->ResponseFail(1001,'您没有权限登录，请登录后台绑定后再查看');
            }

            $result = $this->UserRepository->openidToken($user_info);//token值（这个是和User模型对应的）
            if (empty($result)) {
                return $this->ResponseFail(10001, '登录验证失败');
            }
            $token   = $result["token"] ?? "";
            $tokenId = $result["tokenId"] ?? "";

            if ($user_info->status == 0) {
                return $this->ResponseFail(20102, '您的登录账号已停用，请联系管理员');
            }
            if ($user_info->status == -1) {
                return $this->ResponseFail(20101, '账户已停用');
            }
            if ($user_info->trashed()) {
                return $this->ResponseFail(20101, '账户已注销');
            }

            if (empty(collect($user_info->role)->toArray()) && $user_info->role_id != 1) {
                return $this->ResponseFail(20102, '您的登录账号没有权限，请联系管理员分配权限');
            }

            //获取登录用户数据
            $data['user']  = $this->UserRepository->userDetail($user_info->id, true);
            $data['token'] = $token;
            $this->UserRepository->loginSuccess($user_info);//登录成功
            $data['tokenId'] = $tokenId;
//            dd($data);
            return $this->ResponseSucess($data);
        }catch (\Exception $exception){
            return $this->ResponseFail($exception->getCode(),$exception->getMessage());
        }

    }
}
