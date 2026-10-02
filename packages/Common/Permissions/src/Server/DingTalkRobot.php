<?php
namespace Common\Permissions\Server;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
class DingTalkRobot
{
    protected $webhook;//钉钉机器人地址
    protected $secret;//加签
    public function __construct($robotKey)
    {
        if(is_array($robotKey)){
            $robotKey = $robotKey[0];
        }
        $config = Config::get('permissions.ding_talk.'.$robotKey);
        $this->webhook = $config['webhook'];
        $this->secret = $config['secret'];
    }


    /**
     * 文本消息发送
     * @param $message string
     * @param $atMobiles array
     * @param $isAtAll bool
     * @return array
     */
    public function sendTextMessage(string $message, array $atMobiles = [], bool $isAtAll = false): array
    {
        $data = [
            'msgtype' => 'text',
            'text' => [
                'content' => $message
            ],
            'at' => [
                'atMobiles' => $atMobiles,
                'isAtAll' => $isAtAll
            ]
        ];

       return $this->sendRequest($data);
    }
    public function sendLinkMessage($title, $text, $messageUrl, $picUrl = '')
    {
        $data = [
            'msgtype' => 'link',
            'link' => [
                'title' => $title,
                'text' => $text,
                'messageUrl' => $messageUrl,
                'picUrl' => $picUrl
            ]
        ];

        $this->sendRequest($data);
    }

    public function sendMarkdownMessage($title, $text, $atMobiles = [], $isAtAll = false)
    {
        $data = [
            'msgtype' => 'markdown',
            'markdown' => [
                'title' => $title,
                'text' => $text
            ],
            'at' => [
                'atMobiles' => $atMobiles,
                'isAtAll' => $isAtAll
            ]
        ];

        $this->sendRequest($data);
    }

    public function sendActionCardMessage($title, $text, $singleTitle, $singleURL, $btnOrientation = '0', $hideAvatar = '0')
    {
        $data = [
            'msgtype' => 'actionCard',
            'actionCard' => [
                'title' => $title,
                'text' => $text,
                'hideAvatar' => $hideAvatar,
                'btnOrientation' => $btnOrientation,
                'singleTitle' => $singleTitle,
                'singleURL' => $singleURL
            ]
        ];

        $this->sendRequest($data);
    }

    public function sendFeedCardMessage($links)
    {
        $data = [
            'msgtype' => 'feedCard',
            'feedCard' => [
                'links' => $links
            ]
        ];

        $this->sendRequest($data);
    }
    /**
     * 生成签名
     *
     * @param int $timestamp 时间戳
     * @param string $secret 密钥
     * @return string 签名
     */
    function generateSign($timestamp, $secret) {
        $stringToSign = $timestamp . "\n" . $secret;
        $signature = hash_hmac('sha256', $stringToSign, $secret, true);
        return base64_encode($signature);
    }
    private function sendRequest($data)
    {
        try {
            // 当前时间戳
            $timestamp = time() * 1000; // 钉钉要求毫秒级时间戳
            // 生成签名
            $sign = $this->generateSign($timestamp, $this->secret);
            $this->webhook = $this->webhook."&timestamp=$timestamp&sign=$sign";
            Log::channel('stack')->info('钉钉机器人信息:',[$data]);
            $client = new Client();
            $response = $client->post($this->webhook, [
                'json' => $data,
                'verify' => false // 禁用 SSL 证书验证
            ]);

            $result = json_decode($response->getBody(), true);

            if ($result['errcode'] == 0) {
                return ['code' => 200, 'message' => '消息发送成功', 'data' => $result];
            } else {
                return ['code' => 1, 'message' => '消息发送失败', 'data' => $result];
            }
        } catch (\Exception $e) {
            return ['code' => 500, 'message' => '消息发送失败', 'data' => $e->getMessage()];
        }
    }
}