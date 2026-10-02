<?php
namespace Common\Permissions\Models;



class RoleUser extends BaseModel{

	/**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'role_id', 'user_id','created_at','updated_at','is_system'
    ];
}
