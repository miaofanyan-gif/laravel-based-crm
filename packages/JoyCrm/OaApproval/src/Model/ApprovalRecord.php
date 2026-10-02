<?php

namespace JoyCrm\OaApproval\Model;

/**
 * 审批记录，节点级时间线（OA审批.prd.md §11.7 `oa_approval_record`）
 */
class ApprovalRecord extends CommonModel
{
    protected $table = 'oa_approval_record';

    protected $fillable = [
        'task_id',
        'node_name',
        'node_type',
        'approval_rule',
        'result',
        'start_time',
        'finish_time',
        'sort_order',
    ];

    public $options = [
        'task_id' => ['type' => '='],
    ];

    public $defaultOrder = ['sort_order,asc'];

    public $listFields = [
        'id', 'task_id', 'node_name', 'node_type', 'approval_rule',
        'result', 'start_time', 'finish_time', 'sort_order',
    ];

    public function executors()
    {
        return $this->hasMany(ApprovalRecordExecutor::class, 'record_id', 'id');
    }

    public function fieldChanges()
    {
        return $this->hasMany(ApprovalFieldChange::class, 'record_id', 'id');
    }
}
