<?php

namespace Common\Permissions\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Support\Facades\Redis;
use Common\Permissions\Facades\Users;
use Common\Permissions\Traits\ResponseTrait;
use Common\Permissions\Repositories\PermissionsRepository;
use Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class CheckAuthority
{
    use ResponseTrait;

    /**
     * The authentication guard factory instance.
     *
     * @var \Illuminate\Contracts\Permissions\Factory
     */
    protected $auth;
    /**
     * @var Common\Permissions\Repositories\RoleRepository
     */
    protected $roleAuthority;
    /**
     * @var Common\Permissions\Facades\OperationLog;
     */
    protected $OperationLog;

    /**
     * Create a new middleware instance.
     *
     * @param \Illuminate\Contracts\Auth\Factory $auth
     * @return void
     */
    public function __construct(Auth $auth, PermissionsRepository $roleAuthority)
    {
        //清除多有空行
        $this->auth          = $auth;
        $this->roleAuthority = $roleAuthority;
    }

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @param string|null $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        // 验证用户登录信息是否有效
        if (!Users::checkToken()) {
            return $this->ResponseFail(402, '登录已过期，请重新登录');
        }
        return $next($request);
    }

    public function terminate()
    {

    }
}

