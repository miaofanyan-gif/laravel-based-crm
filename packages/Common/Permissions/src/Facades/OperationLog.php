<?php
namespace Common\Permissions\Facades;

use Illuminate\Support\Facades\Facade;

class OperationLog extends Facade{

    protected static function getFacadeAccessor()
    {
        return 'OperationLog';
    }

}


