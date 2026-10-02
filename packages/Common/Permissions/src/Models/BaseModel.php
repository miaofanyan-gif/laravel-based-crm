<?php

namespace Common\Permissions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class BaseModel extends Model
{
    protected $config;
    protected $connection = "auth_mysql";
    public function __construct(){
        $this->connection =  Config::get("permissions.defalut_database");
        parent::__construct();
    }

}