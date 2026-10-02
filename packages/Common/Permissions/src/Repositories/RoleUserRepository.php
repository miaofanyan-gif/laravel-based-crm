<?php

namespace Common\Permissions\Repositories;

use Common\Permissions\Models\RoleUser;
use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Criteria\RequestCriteria;

use Prettus\Repository\Contracts\RepositoryInterface;
/**
 * Class RoleUserRepository.
 *@package namespace App\Repositories;
 */
class RoleUserRepository extends BaseRepository implements RepositoryInterface
{


    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return RoleUser::class;
    }

    /**
     * Boot up the repository, pushing criteria
     */
    public function boot()
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }

    public function getUserToRoleIDList($userId='')
    {
        return $this->select(['role_id','user_id','is_system'])->where('user_id', $userId)->get()->toArray();
    }

}
