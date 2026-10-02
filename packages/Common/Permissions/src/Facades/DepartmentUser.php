<?php
namespace Common\Permissions\Facades;

use Illuminate\Support\Facades\Facade;

class DepartmentUser extends Facade{

    protected static function getFacadeAccessor()
    {
        return 'DepartmentUser';
    }

}


