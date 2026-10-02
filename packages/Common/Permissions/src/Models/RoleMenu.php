<?php
namespace Common\Permissions\Models;



class RoleMenu extends BaseModel{

	/**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'role_id', 'menu_id', 'subsystem_num'
    ];
}
