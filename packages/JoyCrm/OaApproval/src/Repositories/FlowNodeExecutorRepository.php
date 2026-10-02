<?php

namespace JoyCrm\OaApproval\Repositories;

use JoyCrm\OaApproval\Model\FlowNodeExecutor;
use Prettus\Repository\Eloquent\BaseRepository;

class FlowNodeExecutorRepository extends BaseRepository
{
    public function model()
    {
        return FlowNodeExecutor::class;
    }
}
