<?php

namespace JoyCrm\OaApproval\Model;

/**
 * 审批任务，跨模块聚合核心表（OA审批.prd.md §11.6 `oa_approval_task`）
 */
class ApprovalTask extends CommonModel
{
    protected $table = 'oa_approval_task';

    protected $fillable = [
        'template_id',
        'business_type',
        'business_id',
        'business_no',
        'submitter_id',
        'submit_time',
        'current_node_name',
        'current_approver_id',
        'status',
        'department',
        'created_at',
        'updated_at',
        'is_deleted',
    ];

    public $options = [
        'business_type' => ['type' => '='],
        'business_id'   => ['type' => '='],
        'status'        => ['type' => '='],
        'submitter_id'  => ['type' => '='],
        'is_deleted'    => ['type' => 'eq'],
    ];

    public $defaultOrder = ['id,desc'];

    public $listFields = [
        'id', 'template_id', 'business_type', 'business_id', 'business_no',
        'submitter_id', 'submit_time', 'current_node_name', 'current_approver_id',
        'status', 'department', 'created_at',
    ];

    public function records()
    {
        return $this->hasMany(ApprovalRecord::class, 'task_id', 'id')->orderBy('sort_order');
    }
}
