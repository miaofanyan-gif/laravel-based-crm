<?php

namespace JoyMedia\Toutiao\Server;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use JoyMedia\Toutiao\Traits\ToutiaoRedisKeysTrait;
use Common\Bootstrap\BaseOauth;
use Common\Libs\Http;
use Log;

/**
 * 授权认证
 */
class AgentOauth  extends BaseOauth{
    use ToutiaoRedisKeysTrait;
    public $app_id;
    private $app_secret;
    private $redis;
    public function __construct()
    {
        $media = config('media.toutiao.application');
        $this->app_id = $media['app_id'];
        $this->app_secret = $media['secret'];
        $this->redis = Redis::connection('media_redis');
    }
    /**
     * 获取 Authorization Code
     * @param string $state
     * @param string $scope
     */
    public function getAuthorization($state = '', $scope = '')
    {
        $url = 'https://open.oceanengine.com/audit/oauth.html';
        $redirect_uri = 'http://'.$_SERVER['HTTP_HOST'].'/oauth/toutiao/agent/authorize';
        $data = array(
            'app_id'=>$this->app_id,
            'state' => $state,
            'redirect_uri' => $redirect_uri,
            'scope' => $scope,
            'material_auth'=>1
        );
        header('Location:'.$url.'?'.http_build_query($data));
    }



    /**
     * 获取AccessToken
     * @param boolean $grant_type 请求的类型
     * @param null $auth_code
     * @param null $refresh_token
     * @return \Common\Libs\json
     */
    public function getAccessToken($grant_type = true, $auth_code = null, $refresh_token = null)
    {
        $this->http = new Http();
        if($grant_type === true){  //获取access token
            $url = 'https://ad.oceanengine.com/open_api/oauth2/access_token/';
            $data = array(
                'app_id' => $this->app_id,
                'secret' => $this->app_secret,
                'grant_type' => 'auth_code',
                'auth_code' => $auth_code
            );
        }else if($grant_type === false){    //更新access token
            $url = 'https://ad.oceanengine.com/open_api/oauth2/refresh_token/';
            $data = array(
                'app_id' => $this->app_id,
                'secret' => $this->app_secret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $refresh_token
            );
        }
        $this->http->headers = array(
            "Content-Type：application/json",
        );
        Log::channel('toutiao_token')->info('token 请求参数',[$url,$data]);
        for ($i = 0; $i <  10; $i++) {
            $result = $this->http->post($url, $data);
            $result = json_decode($result, true);
            Log::channel('toutiao_token')->info('token 请求结果',[$result]);
            if (isset($result['code']) && $result['code'] == 0) {
                break;
            } else if (isset($result['code']) && in_array($result['code'], [40100, 40110, 40130])) {
                usleep(rand(2000000, 3000000));
                continue;
            } else {
                throw new \Exception('get：' . $result['message'], $result['code']??500);
                break;
            }
        }
        return $result;
    }

    /**
     * 刷新access_token
     */
    public function refreshAccessToken()
    {
        Log::channel('toutiao_token')->info('刷新 头条已经授权的 token开始');
        set_time_limit(0);
        //查询需要刷新的头条token
        $detail = $this->redis->hgetall($this->getTokenDetailsKey($this->app_id));
        //获取服务商AccessToken
        $result = $this->getAccessToken(false, null, $detail['refresh_token']);

        if ((empty($result)) || ($result['code'] != 0)) {
            return ['code' => 1, 'message' => 'access_token refresh failed', 'data' => $result];
        }
        $accesstokeninfo = $result['data'];
        $this->setAgentToken($accesstokeninfo);
        Log::channel('toutiao_token')->info('刷新 头条已经授权 token结束');
        return ['code' => 0, 'message' => 'access_token refresh successed', 'data' => $accesstokeninfo];
    }

    /**
     * 存储token
     * @param $accesstokeninfo
     */
    public function setAgentToken($accesstokeninfo){
        //同步至redis
        $this->redis->setex($this->getToutiaoTokenKey($this->app_id),2*86400, $accesstokeninfo['access_token']);
        $accesstokeninfo['advertiser_ids'] = isset($accesstokeninfo['advertiser_ids'])?json_encode($accesstokeninfo['advertiser_ids']):null;
        $detail_key = $this->getTokenDetailsKey($this->app_id);
        $accesstokeninfo['updated_at'] = date('Y-m-d H:i:s');
        $this->redis->hmset($detail_key, $accesstokeninfo);
        $this->redis->EXPIRE($detail_key,30*86400);
    }

    /**
     * 获取token
     * @return mixed
     */
    public function getAgentToken(){
        $key = $this->getToutiaoTokenKey($this->app_id);
        if(!$this->redis->EXISTS($key)){
            return null;
        }
        return $this->redis->get($key);
    }


    /**
     * 获取授权用户
     */
    public function  getUserInfo($accessToken){
        $this->http = new Http();
        $url='https://ad.oceanengine.com/open_api/2/user/info/';
        $data = array(
            'access_token'=>$accessToken
        );
        $this->http->headers=array(
            "Access-Token: " . $accessToken,
        );
        $result =  $this->http->get($url, $data);
        Log::channel('toutiao_token')->info('授权用户信息',[$result]);
        $result = json_decode($result, true);
        if($result['code']===0){
            return $result['data'];
        }
        return $result;
    }


}
