<?php

namespace JoyCrm\OaApproval\Repositories;

use JoyCrm\OaApproval\Model\ApprovalRecord;
use Prettus\Repository\Eloquent\BaseRepository;

class ApprovalRecordRepository extends BaseRepository
{
    public function model()
    {
        return ApprovalRecord::class;
    }

    /**
     * 当前活动节点记录：result 还在进行中的那一条（同一时刻只应有一条）
     */
    public function currentActive(int $taskId): ?ApprovalRecord
    {
        return $this->where('task_id', $taskId)
            ->whereIn('result', ['in_progress', 'pending']) // 枚举代码见 prd/sql/oa_dict.json
            ->orderByDesc('sort_order')
            ->first();
    }

    /**
     * 某个 sort_order 位置上最近一次的历史记录（可能因退回而出现多条，取最新一条）
     */
    public function latestAtSortOrder(int $taskId, int $sortOrder): ?ApprovalRecord
    {
        return $this->where('task_id', $taskId)
            ->where('sort_order', $sortOrder)
            ->orderByDesc('id')
            ->first();
    }

    public function timelineOf(int $taskId)
    {
        return $this->with(['executors', 'fieldChanges'])
            ->where('task_id', $taskId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
