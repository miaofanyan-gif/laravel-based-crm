<?php

namespace Common\Bootstrap;

use Common\Libs\Common;
use Common\Libs\Http;
use Log;
 class BaseMedia
{

     public $header=array();
     public $_header=null;
     public $medianame='';
     protected $uri='';
     public $params='';
     public $api;
     protected $data;
     public $request_mode;//请求方式
     public $status;//请求状态
     public $errorMessage;//错误信息

     public function  __construct($advertiser_id=''){
         $this->setToken($advertiser_id);
         $this->http=new Http();
         $this->connection();
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
     public function get($api, $data = array())
     {
         $this->api=$api;
         $this->data=$data;
         $this->http->headers = $this->_header;
         $this->params=$data;
         $this->url($api);
         $this->request_mode='get';
         $result = $this->http->get($this->uri, $this->params);
         return $this->result($result);

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


     /**
      * 文件上传
      * @param $api
      * @param $data
      * @return mixed
      * @throws \Exception
      */
     public function upload($api, $data){
         $this->api = $api;
         $this->http->headers = array('Content-Type: multipart/form-data', 'Access-Token: ' . $this->token);;
         $this->url($api);
         $this->request_mode = 'post';
         $result = $this->http->post($this->uri, $data);
         return $this->uploadResult($result);
     }
}
