<?php
namespace Common\Permissions\Facades;

use Illuminate\Support\Facades\Facade;

class Department extends Facade{

    protected static function getFacadeAccessor()
    {
        return 'Department';
    }

}


