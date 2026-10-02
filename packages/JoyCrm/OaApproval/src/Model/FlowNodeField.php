<?php

namespace JoyCrm\OaApproval\Model;

/**
 * 节点可编辑字段配置（OA审批.prd.md §11.5 `oa_flow_node_field`）
 */
class FlowNodeField extends CommonModel
{
    protected $table = 'oa_flow_node_field';

    protected $fillable = [
        'node_id',
        'field_name',
        'field_key',
        'field_type',
        'is_required',
        'default_rule',
        'sort_order',
    ];

    public $options = [
        'node_id' => ['type' => '='],
    ];

    public $defaultOrder = ['sort_order,asc'];

    public $listFields = [
        'id', 'node_id', 'field_name', 'field_key', 'field_type',
        'is_required', 'default_rule', 'sort_order',
    ];
}
