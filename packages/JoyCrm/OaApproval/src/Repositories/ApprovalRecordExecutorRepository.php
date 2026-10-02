<?php

namespace JoyCrm\OaApproval\Repositories;

use JoyCrm\OaApproval\Model\ApprovalRecordExecutor;
use Prettus\Repository\Eloquent\BaseRepository;

class ApprovalRecordExecutorRepository extends BaseRepository
{
    public function model()
    {
        return ApprovalRecordExecutor::class;
    }

    public function findPending(int $recordId, int $executorId): ?ApprovalRecordExecutor
    {
        return $this->where('record_id', $recordId)
            ->where('executor_id', $executorId)
            ->where('result', 'pending') // 枚举代码见 prd/sql/oa_dict.json
            ->first();
    }

    /**
     * 或签通过 / 会签驳回时的并发拦截：把同一记录下其余「待处理」批量置为终态
     * （§11.8：不单独建状态值，直接复用 已办结/completed 表达"无需操作"）
     */
    public function closeRestAsDone(int $recordId, int $exceptExecutorId): void
    {
        $this->where('record_id', $recordId)
            ->where('executor_id', '<>', $exceptExecutorId)
            ->where('result', 'pending')
            ->update(['result' => 'completed', 'operate_time' => now()]);
    }
}
