<?php
namespace Common\Permissions\Models;



class SaleUser extends BaseModel{

    protected $table = 'sale_users';
    protected $fillable = ['user_id', 'sale_id','ding_user_id'];
}
