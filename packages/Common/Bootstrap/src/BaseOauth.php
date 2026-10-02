<?php

namespace Common\Bootstrap;

abstract class BaseOauth
{

    //刷新token
    abstract public function refreshAccessToken();
}
