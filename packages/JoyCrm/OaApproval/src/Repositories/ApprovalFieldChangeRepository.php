<?php

namespace JoyCrm\OaApproval\Repositories;

use JoyCrm\OaApproval\Model\ApprovalFieldChange;
use Prettus\Repository\Eloquent\BaseRepository;

class ApprovalFieldChangeRepository extends BaseRepository
{
    public function model()
    {
        return ApprovalFieldChange::class;
    }
}
