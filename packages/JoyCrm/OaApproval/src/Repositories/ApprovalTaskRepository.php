<?php

namespace JoyCrm\OaApproval\Repositories;

use Common\Exceptions\RepositoryException;
use JoyCrm\OaApproval\Model\ApprovalTask;
use JoyCrm\OaApproval\Model\ApprovalRecord;
use JoyCrm\OaApproval\Model\ApprovalRecordExecutor;
use Prettus\Repository\Eloquent\BaseRepository;

class ApprovalTaskRepository extends BaseRepository
{
    public function model()
    {
        return ApprovalTask::class;
    }

    public function findOrFail($id): ApprovalTask
    {
        $task = $this->where('id', $id)->where('is_deleted', 0)->first();
        if (empty($task)) {
            throw new RepositoryException('审批任务不存在', 10001);
        }
        return $task;
    }

    /**
     * 幂等：同一 business_type+business_no 已存在任务则直接返回（§13.1 幂等与失败处理）
     */
    public function findByBusinessNo(string $businessType, string $businessNo): ?ApprovalTask
    {
        return $this->where('business_type', $businessType)
            ->where('business_no', $businessNo)
            ->first();
    }

    public function findByBusiness(string $businessType, $businessId): ?ApprovalTask
    {
        return $this->where('business_type', $businessType)
            ->where('business_id', $businessId)
            ->where('is_deleted', 0)
            ->first();
    }

    /**
     * 待我审批列表：当前用户是某未办结节点执行人明细里 result=待处理 的那一条
     * $options 表达不了跨表 join，这里直接手写查询（backend-conventions.md §3 允许的例外）
     */
    public function pendingForUser(int $userId, int $perPage = 15)
    {
        return $this->where('is_deleted', 0)
            ->whereIn('status', ['in_progress', 'pending']) // 枚举代码见 prd/sql/oa_dict.json
            ->whereHas('records', function ($query) use ($userId) {
                $query->whereIn('result', ['in_progress', 'pending'])
                    ->whereHas('executors', function ($q) use ($userId) {
                        $q->where('executor_id', $userId)->where('result', 'pending');
                    });
            })
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
