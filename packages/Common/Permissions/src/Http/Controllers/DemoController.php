<?php

namespace Common\Permissions\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Common\Permissions\Common\RepositoryException;

class DemoController
{
    /**
     * 机器人发送文本消息demo
     */
    public function sendFeedCardMessage(Request $request)
    {
        try {
            $webhook = $request->get('webhook');//机器人地址+
            $secret = $request->get('secret');//加签
            // 发送文本消息
         //   print_r($webhook);

            $sendTextMessage = \DingTalkRobot::withWebhook("robot1")->sendTextMessage('中华人民共和国中央人民政府在今天成立啦', [15321633121], false);
            return response()->json(['code' => 200, 'msg' => "success", 'data' =>"消息发送成功"]);
        } catch (RepositoryException $exception) {
            return response()->json(['code' => 500, 'msg' => "error", 'data' => $exception->getMessage()]);
        }
    }
}
