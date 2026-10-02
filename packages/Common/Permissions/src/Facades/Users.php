<?php
namespace Common\Permissions\Facades;

use Illuminate\Support\Facades\Facade;

class Users extends Facade{

    protected static function getFacadeAccessor()
    {
        return 'Users';
    }

}


