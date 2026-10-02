<?php

namespace JoyCrm\OaApproval\Model;

/**
 * 审批流节点（OA审批.prd.md §11.3 `oa_flow_node`）
 */
class FlowNode extends CommonModel
{
    protected $table = 'oa_flow_node';

    protected $fillable = [
        'template_id',
        'node_name',
        'node_type',
        'approval_rule',
        'field_edit_mode',
        'sort_order',
        'created_at',
        'updated_at',
    ];

    public $options = [
        'template_id' => ['type' => '='],
    ];

    public $defaultOrder = ['sort_order,asc'];

    public $listFields = [
        'id', 'template_id', 'node_name', 'node_type', 'approval_rule',
        'field_edit_mode', 'sort_order',
    ];

    public function executors()
    {
        return $this->hasMany(FlowNodeExecutor::class, 'node_id', 'id')->orderBy('sort_order');
    }

    public function fields()
    {
        return $this->hasMany(FlowNodeField::class, 'node_id', 'id')->orderBy('sort_order');
    }
}
