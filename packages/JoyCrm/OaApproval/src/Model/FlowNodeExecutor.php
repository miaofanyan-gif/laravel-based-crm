<?php

namespace JoyCrm\OaApproval\Model;

/**
 * 节点执行人配置（OA审批.prd.md §11.4 `oa_flow_node_executor`）
 */
class FlowNodeExecutor extends CommonModel
{
    protected $table = 'oa_flow_node_executor';

    protected $fillable = [
        'node_id',
        'exec_type',
        'exec_target_id',
        'sort_order',
    ];

    public $options = [
        'node_id' => ['type' => '='],
    ];

    public $defaultOrder = ['sort_order,asc'];

    public $listFields = ['id', 'node_id', 'exec_type', 'exec_target_id', 'sort_order'];
}
