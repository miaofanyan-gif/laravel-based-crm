<?php

namespace JoyCrm\OaApproval\Model;

/**
 * 节点执行人操作明细（OA审批.prd.md §11.8 `oa_approval_record_executor`）
 */
class ApprovalRecordExecutor extends CommonModel
{
    protected $table = 'oa_approval_record_executor';

    protected $fillable = [
        'record_id',
        'executor_id',
        'result',
        'operate_time',
        'comment',
    ];

    public $options = [
        'record_id'   => ['type' => '='],
        'executor_id' => ['type' => '='],
    ];

    public $defaultOrder = ['id,asc'];

    public $listFields = [
        'id', 'record_id', 'executor_id', 'result', 'operate_time', 'comment',
    ];

    // 本表不冗余存姓名（oa_dict.json 设计说明），展示时按 executor_id 联查 Common\Permissions\Models\User
    public function executor()
    {
        return $this->belongsTo(\Common\Permissions\Models\User::class, 'executor_id', 'id');
    }
}
