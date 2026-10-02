<?php

namespace Common\Permissions\Traits;

use Common\Permissions\Common\Code;

trait ResponseTrait
{
    /**
     * @param int $code
     * @return \Illuminate\Http\JsonResponse
     */
    public function ResponseFail($code = '10001',$msg='')
    {
        $msg = $msg ? $msg : Code::code($code);
        return response()->json(['code' => $code, 'msg' => $msg]);
    }

    public function ResponseSucess($data = '',$msg='SUCCESS')
    {
        //Common::printR($data);
        $time = round(microtime(true) - constant('LARAVEL_START'), 4);
        return response()->json(['code' => 200, 'msg' => $msg, 'handdle_time'=>$time, 'data' => $data ?? '']);
    }

}
