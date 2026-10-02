<?php

namespace JoyCrm\OaApproval\Repositories;

use JoyCrm\OaApproval\Model\FlowNodeField;
use Prettus\Repository\Eloquent\BaseRepository;

class FlowNodeFieldRepository extends BaseRepository
{
    public function model()
    {
        return FlowNodeField::class;
    }

    public function requiredKeysOf(int $nodeId): array
    {
        return $this->where('node_id', $nodeId)
            ->where('is_required', 1)
            ->pluck('field_key')
            ->toArray();
    }
}
