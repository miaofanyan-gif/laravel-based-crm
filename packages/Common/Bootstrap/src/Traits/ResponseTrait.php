<?php

namespace Common\Bootstrap\Traits;

use Common\Libs\Code;
use Common\Libs\Common;

trait ResponseTrait
{
    /**
     * @param int $code
     * @return \Illuminate\Http\JsonResponse
     */
    public function ResponseFail($code = '10001',$msg='')
    {
        $msg = $msg ? $msg : Code::code($code);
        return response()->json(['code' => $code, 'msg' => $msg,'data' =>'']);
    }

    public function ResponseSucess($data = '',$msg='SUCCESS')
    {
        //Common::printR($data);
        $time = round(microtime(true) - constant('LARAVEL_START'), 4);
        return response()->json(['code' => 200, 'msg' => $msg, 'handle_time'=>$time, 'data' => $data ?? '']);
    }


    /**
     * @param int $code 旧版接口正确返回状态码为10000
     * @param string $data
     * @param string $msg
     * @return \Illuminate\Http\JsonResponse
     */
    public function ResponseApiSucess( $code = 10000,$data = '', $msg = 'SUCCESS')
    {
        //Common::printR($data);
        $time = round(microtime(true) - constant('LARAVEL_START'), 2);
        return response()->json(['code' => $code, 'msg' => $msg, 'time'=>$time,'data' => $data ?? '']);
    }
}
