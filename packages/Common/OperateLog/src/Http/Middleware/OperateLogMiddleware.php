<?php

namespace Common\OperateLog\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Common\OperateLog\Facades\OperateLog;

class OperateLogMiddleware
{

    /**
     * Handle an incoming request.
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // 写入前置日志
        OperateLog::writeBefore();

        $responses = $next($request);

        // 写入日志后置
        OperateLog::writeAfter();

        return $responses;
    }
}

