<?php

namespace JoyMedia\Media;

abstract class BaseOauth
{

    //刷新token
    abstract public function refreshAccessToken();
}
