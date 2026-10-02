<?php

namespace Common\Bootstrap\Interfaces;

interface MediaInterface
{
    const domain='';
    public function connection();
    public function setToken($service_id);
    public function get($url,$data);
    public function post($url, $data);

}
