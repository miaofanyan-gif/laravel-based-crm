<?php
namespace Common\Permissions\Models;



class AreaUser extends BaseModel{

	/**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'area_id', 'user_id','created_at','updated_at','ding_user_id'
    ];
}
