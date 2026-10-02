<?php
namespace JoyMedia\Media;

/**
 *
 *  HTTP 请求类
 *
 */

class Http{

    /**
     * 请求的url
    */
    private $_uri;

    /**
     * 请求的方法
    */
    private $_method;

    /**
     * header
    */
    public $headers = array();
    public $encoding = '';
    /**
 * error
 */
    public $error ;

    /**
     * 请求的参数
    */
    private $_params = array();


    public function __construct($headers = [])
    {
        $this->headers = $headers;
    }

    /**
     * post请求处理
     * @param type $params 接收参数
     * @param type $apiurl 接口地址
     * @return json $content
     */
    public function post($apiurl, $params)
    {
        $this->setMethod('post')->setUrl($apiurl)->setParams($params)->url();
        $content = $this->curlPost($apiurl);
        return $content;
    }

    /**
     * post请求处理 json数据传输
     * @param type $params 接收参数
     * @param type $apiurl 接口地址
     * @return json $content
     */
    public function postJson($apiurl, $params)
    {
        $this->setMethod('post')->setUrl($apiurl)->setParams($params);
        $content = $this->curlPost($apiurl);
        return $content;
    }

    /**
     * get请求处理
     * @param type $params 接收参数
     * @param type $apiurl 接口地址
     * @return json $content
     */
    public function get($apiurl, $params = NULL)
    {
        $string = $this->setMethod('get')->setUrl($apiurl)->setParams($params)->url();

        $content = $this->curlGet($string);
        return $content;
    }



    public function setParams($param)
    {
        $this->_params = $param;
        return $this;
    }


    public function filterParameters($params)
    {

        $filtered = array();
        $query = http_build_query($params);
        if (strlen($query) > 0)
        {
            $params = explode('&', $query);
            foreach ($params as $p)
            {
                @list($name, $value) = explode('=', $p, 2);
                if (!strlen($name))
                {
                    continue;
                }
                $name = urldecode($name);
                $value = urldecode($value);
                if (array_key_exists($name, $filtered))
                {
                    if (is_array($filtered[$name]))
                    {
                        $filtered[$name][] = $value;
                    }
                    else
                    {
                        $filtered[$name] = array($filtered[$name], $value);
                    }
                }
                else
                {
                    $filtered[$name] = $value;
                }
            }
        }
        return $filtered;
    }

    public function setMethod($method)
    {
        $this->_method = strtoupper($method);
        return $this;
    }

    public function setUrl($uri)
    {
        $this->_uri = $uri;
        return $this;
    }

    function curlPost($url, $post_data = array(), $cookie = '', $timeout=60)
    {
        //dd($post_data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        if ($this->_params)
        {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->_params);
        }
        if ($cookie)
        {
            curl_setopt($ch, CURLOPT_COOKIE , $cookie);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);// 设置header
        $result = curl_exec($ch);
        $info = curl_getinfo($ch);
        $error = curl_error($ch);
        $this->error=$error;
        curl_close($ch);
        return $result;
    }


    public function url(){

        if($this->_params)
        {
            if(strpos($this->_uri, '?')){
                $url = $this->_uri."&".http_build_query($this->_params);
            }else{
                $url = $this->_uri."?".http_build_query($this->_params);
            }
        }
        else
        {
            $url = $this->_uri;
        }
        return $url;
    }


    function curlGet($url, $ref_url ='', $data='', $cookie = '', $timeout=60){
        $ch = curl_init();
        if ($ref_url != '')
        {
            curl_setopt($ch, CURLOPT_REFERER, $ref_url);
        }
        /*if ($this->_params)
        {
           curl_setopt($ch, CURLOPT_POST, true);
           curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($this->_params));
        }*/
        if ($cookie)
        {
            curl_setopt($ch, CURLOPT_COOKIE , $cookie);
        }
        $header = "application/x-www-form-urlencoded";
        curl_setopt($ch, CURLOPT_VERBOSE,        FALSE);                // Minimize logs
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-type: $header"));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);//是否展示头
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        if($this->encoding) {
//            curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
            curl_setopt($ch, CURLOPT_ENCODING, $this->encoding);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);// 设置header
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        $result = curl_exec($ch);
        $info = curl_getinfo($ch);
        $error = curl_error($ch);
        $this->error=$error;
// 获得响应结果里的：头大小
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
// 根据头大小去获取头信息内容
        $this->response_header=substr($result, 0, $headerSize);
        $result= substr($result,  $headerSize);

        curl_close($ch);
        return $result;
    }


    function curlPut($url, $post_data = array(), $cookie = '', $timeout=60)
    {
        $ch = curl_init();
        if ($post_data)
        {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        }
        // 请求头，可以传数组
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_HTTPHEADER,$this->headers);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 执行后不直接打印出来
        curl_setopt ($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 跳过证书检查
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // 不从证书中检查SSL加密算法是否存在
        $result = curl_exec($ch); //执行并获取HTML文档内容
        $info = curl_getinfo($ch);
        $error = curl_error($ch);
        $this->error=$error;
        curl_close($ch); //释放curl句柄
        return $result;
    }



}
