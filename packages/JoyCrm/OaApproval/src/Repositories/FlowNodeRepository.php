<?php

namespace JoyCrm\OaApproval\Repositories;

use JoyCrm\OaApproval\Model\FlowNode;
use Prettus\Repository\Eloquent\BaseRepository;

class FlowNodeRepository extends BaseRepository
{
    public function model()
    {
        return FlowNode::class;
    }

    public function findByTemplateAndSort(int $templateId, int $sortOrder): ?FlowNode
    {
        return $this->where('template_id', $templateId)
            ->where('sort_order', $sortOrder)
            ->first();
    }

    /**
     * 同名节点定位（用于审批记录 node_name 快照 -> 节点配置反查，见 ApprovalEngine 注释）
     */
    public function findByTemplateAndName(int $templateId, string $nodeName): ?FlowNode
    {
        return $this->where('template_id', $templateId)
            ->where('node_name', $nodeName)
            ->first();
    }
}
