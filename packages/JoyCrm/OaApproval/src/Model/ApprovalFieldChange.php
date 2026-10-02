<?php

namespace JoyCrm\OaApproval\Model;

/**
 * 字段修改留痕（OA审批.prd.md §11.9 `oa_approval_field_change`）
 */
class ApprovalFieldChange extends CommonModel
{
    protected $table = 'oa_approval_field_change';

    protected $fillable = [
        'record_id',
        'field_key',
        'field_name',
        'old_value',
        'new_value',
        'operator_id',
        'operate_time',
    ];

    public $options = [
        'record_id' => ['type' => '='],
    ];

    public $defaultOrder = ['id,asc'];

    public $listFields = [
        'id', 'record_id', 'field_key', 'field_name', 'old_value', 'new_value',
        'operator_id', 'operate_time',
    ];
}
