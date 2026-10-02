<?php

namespace JoyCrm\OaApproval\Model;

/**
 * 审批流模板（OA审批.prd.md §11.2 `oa_flow_template`）
 */
class FlowTemplate extends CommonModel
{
    protected $table = 'oa_flow_template';

    protected $fillable = [
        'name',
        'business_type',
        'status',
        'remark',
        'creator_id',
        'created_at',
        'updated_at',
        'is_deleted',
    ];

    public $options = [
        'name'          => ['type' => 'like', 'field' => ['name']],
        'business_type' => ['type' => '='],
        'status'        => ['type' => '='],
        'is_deleted'    => ['type' => 'eq'], // 0/1 都是有效值，用 eq 避免 0 值陷阱（backend-conventions.md §4）
    ];

    public $defaultOrder = ['id,desc'];

    public $listFields = [
        'id', 'name', 'business_type', 'status', 'remark',
        'creator_id', 'created_at', 'updated_at',
    ];

    public function nodes()
    {
        return $this->hasMany(FlowNode::class, 'template_id', 'id')->orderBy('sort_order');
    }
}
