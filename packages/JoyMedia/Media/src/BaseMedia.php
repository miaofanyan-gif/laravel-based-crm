<?php

namespace JoyMedia\Media;

use Common\Libs\Common;
use Log;
 class BaseMedia
{

     public $header=array();
     public $medianame='';
     //代理商id
     protected $advertiser_id;
     protected $default_service_id;
     protected $uri='';
     public $params='';
     public $api;
     protected $data;
     protected $request_api;//请求api
     public $request_mode;//请求方式
     public $status;//请求状态
     public $errorMessage;//错误信息

     public function  __construct($advertiser_id=''){
         $this->setToken($advertiser_id);
         $this->http = new Http();
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
         //Log::channel($this->medianame)->info('媒体API请求结果-BaseMedia:',[$str]);
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
     public function postResult($str, $single){
         //Log::channel($this->medianame)->info('媒体API请求结果-BaseMedia:',[$str]);
         $array = json_decode($str, true);
         if (!is_array($array)) {
             return $this->errorResult($str);
         }
         return  $this->filterPostResult($array, $single);
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
         return  $this->filterUploadResult($array);
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
      * post过滤接口返回结果结果
      * @param $result
      * @return mixed
      */
     public function filterPostResult($result, $single){
         return $result;
     }

     /**
      * 接口json解析错误处理
      * @param $str
      * @return mixed
      */
     public function errorResult($str){
         $data=[
             'medianame'=>$this->medianame,
             'uri'=>$this->uri,
             'params'=>$this->params,
             'result'=>$str
         ];
         $this->status=false;
         $this->message=$str;
         $this->webMessage='巨量接口返回错误，请稍后重试~';
         //event(new \JoyGc\Unidesk\Events\ReportTokenError($this->advertiser_id,$data,'媒体接口请求错误'));

         //Log::channel($this->medianame)->error('媒体API请求错误！data:',$data);

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

     public function post($api, $data, $single='oceanengine'){
         $this->api = $api;
         $this->http->headers = $this->_header;
         Log::channel('oceanengine')->info('接口请求数据:', [$data]);
         $this->params = json_encode($data);
         $this->url($api);
         $this->request_mode = 'post';
         $result = $this->http->postJson($this->uri, $this->params);
         //$result = '{"code": 0, "message": "OK", "request_id": "202110131731110102121641302C000499", "data": {"campaign_id": 1714126452614249}}';
         //$result = '{"code": 0, "message": "OK", "request_id": "202110151113530102060960445A16AEAD", "data": {"ad_id": 1717303399506954}}';
         //  echo $result;
         //$result='{"message": "OK", "code": 0, "data": {"task_type": "REPORT", "task_id": 286900879, "task_params": {"group_by": ["STAT_GROUP_BY_CREATIVE_ID"], "end_date": "2021-08-11", "start_date": "2021-08-11", "order_field": "cost", "order_type": "DESC"}, "err_msg": "", "advertiser_id": 111468773737, "create_time": "2021-08-12 09:08:36", "task_name": "d_66178220648_585642462", "file_size": 9455, "task_status": "ASYNC_TASK_STATUS_COMPLETED"}, "request_id": "202108121537590102121720293004BE36"}';
         return $this->postResult($result, $single);
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
