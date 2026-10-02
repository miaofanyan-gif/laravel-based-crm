<?php

namespace Common\Bootstrap;

use Common\Libs\Common;
use Common\Libs\Http;
use GuzzleHttp\Client;
use Common\Exceptions\RepositoryException;
use Log;
 class BaseClient
{
     protected Client $client;
     public $header=array();
     public $uri;
     public $params;
     public $code_field = 'code';
     public $medianame;
     public $api;
     protected $data;
     public $status;//请求状态
     public $errorMessage;//错误信息

     public function  __construct($advertiser_id=''){

     }

     /**
      * 拼接 mapi 的url
      * @param $api
      * @return \Illuminate\Config\Repository|\Illuminate\Contracts\Foundation\Application|\Laravel\Lumen\Application|mixed
      * @throws \Exception
      */
     public function url($api){
         $this->uri = config('media.' . $this->medianame . '.apis.' . $api);
         //如果没找到uri 返回错误
         if (!$this->uri) {
             //Log::channel($this->medianame)->error('not defined uri ,pls check conf/media.php :'.'media.'.$this->medianame.'.apis.'.$api);
             throw new \Exception('not defined uri ,pls check conf/media.php :' . 'media.' . $this->medianame . '.apis.' . $api);
         }
         return  $this->uri;
     }

     /**
      * tokne验证
      * 如果token 错误发邮件
      */
     public function checkToken(){
         if (!$this->token) {//token错误
             $data = [
                 'medianame' => $this->medianame,
                 'uri' => $this->uri,
                 'params' => $this->params,
                 'result' => 'Token获取失败',
             ];
             sleep(1);
         }
     }


     /**
      * 结果处理，
      * 可以自定义接口返回的错误码和接口报错处理
      * @param $str
      * @return mixed
      */
     public function result($str){
         $array = json_decode($str, true);
         if (!is_array($array)) {
             return $this->errorResult($str);
         }
         return  $this->filterResult($array);
     }


     /**
      * post结果处理，
      * 可以自定义接口返回的错误码和接口报错处理
      * @param $str
      * @return mixed
      */
     public function uploadResult($str){
         $array = json_decode($str, true);
         if (!is_array($array)) {
             return $this->errorResult($str);
         }
         return  $this->result($array);
     }

     /**
      * 过滤接口返回结果结果
      * @param $result
      * @return mixed
      */
     public function filterResult($result){
         return $result;
     }


     /**
      * 接口json解析错误处理
      * @param $str
      * @return mixed
      */
     public function errorResult($str){
         $this->status=false;
         return collect([]);
     }
     /**
      * get方法
      */
     public function get($api, $options = array())
     {
         try {
             for ($i = 0; $i < 15; $i++) {
                 $response = $this->client->get('GET', $this->uri($api), $options);
                 $result = $this->handleResponse($response);
                 if($result){

                 }

             }



             if (json_last_error() !== JSON_ERROR_NONE) {
                 throw new RepositoryException('Failed to decode JSON response');
             }

             return $data;
         } catch (\Exception $exception) {
             throw new RepositoryException($exception->getMessage());
         }

     }


     /**
      * 处理请求返回结果
      * @param $response
      * @return false|mixed
      * @throws \Common\Exceptions\RepositoryException
      */
     protected function handleResponse($response)
     {
         try {
             $contents = $response->getBody()->getContents();
             $data     = json_decode($contents, true);

             if (json_last_error() !== JSON_ERROR_NONE) {
                 throw new RepositoryException('Failed to decode JSON response');
             }

             return $data;
         } catch (\Exception $exception) {
             throw new RepositoryException($exception->getMessage());
         }
     }

     public function post($api, $data){
         $this->api = $api;
         $this->http->headers = $this->_header;
         $this->params = json_encode($data);
         $this->url($api);
         $this->request_mode = 'post';
         $result = $this->http->postJson($this->uri, $this->params);
         return $this->result($result);
     }


}
