<?php

namespace JoyCrm\OaApproval\Http\Controllers;

use Common\Bootstrap\BaseController;
use Common\Exceptions\RepositoryException;
use Illuminate\Http\Request;
use JoyCrm\OaApproval\Repositories\FlowNodeExecutorRepository;
use JoyCrm\OaApproval\Repositories\FlowNodeFieldRepository;
use JoyCrm\OaApproval\Repositories\FlowNodeRepository;
use JoyCrm\OaApproval\Repositories\FlowTemplateRepository;

/**
 * 审批流配置（后台配置端，OA审批.prd.md §六 / §12.1）
 */
class FlowTemplateController extends BaseController
{
    protected $templateRepository;
    protected $nodeRepository;
    protected $executorRepository;
    protected $fieldRepository;

    public function __construct(
        FlowTemplateRepository $templateRepository,
        FlowNodeRepository $nodeRepository,
        FlowNodeExecutorRepository $executorRepository,
        FlowNodeFieldRepository $fieldRepository
    ) {
        $this->templateRepository = $templateRepository;
        $this->nodeRepository = $nodeRepository;
        $this->executorRepository = $executorRepository;
        $this->fieldRepository = $fieldRepository;
        parent::__construct($templateRepository);
    }

    /**
     * 流程列表（§6.1）
     */
    public function index(Request $request)
    {
        return $this->ResponseSucess($this->list($request));
    }

    /**
     * 流程详情，含全部节点/执行人/字段配置，按 sort_order 排序返回（§12.1）
     */
    public function detail(Request $request)
    {
        try {
            $template = $this->templateRepository->detailWithNodes($request->input('id'));
            return $this->ResponseSucess($template);
        } catch (RepositoryException $e) {
            return $this->ResponseFail($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 新建流程（请求体 nodes 数组下标即节点顺序，§12.1）
     */
    public function add(Request $request)
    {
        try {
            $data = $request->all();
            if (empty($data['name']) || empty($data['business_type'])) {
                throw new RepositoryException('流程名称和适用单据类型必填', 10001);
            }
            if ($this->templateRepository->where('name', $data['name'])->count()) {
                throw new RepositoryException('流程名称已存在', 10001);
            }

            $template = $this->templateRepository->create([
                'name'          => $data['name'],
                'business_type' => $data['business_type'],
                'status'        => $data['status'] ?? 'enabled', // 枚举代码见 prd/sql/oa_dict.json
                'remark'        => $data['remark'] ?? null,
                'creator_id'    => auth()->id(),
            ]);

            $this->writeNodes($template->id, $data['nodes'] ?? []);

            return $this->ResponseSucess(['id' => $template->id]);
        } catch (RepositoryException $e) {
            return $this->ResponseFail($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 编辑流程：nodes 整体覆盖式更新——先删旧配置，再按请求体重新写入（§12.1）
     */
    public function edit(Request $request)
    {
        try {
            $data = $request->all();
            $template = $this->templateRepository->findById($data['id']);
            if (empty($template)) {
                throw new RepositoryException('审批流程不存在', 10001);
            }
            if (!empty($data['name']) && $this->templateRepository->where('name', $data['name'])->where('id', '<>', $template->id)->count()) {
                throw new RepositoryException('流程名称已存在', 10001);
            }

            $template->name = $data['name'] ?? $template->name;
            $template->business_type = $data['business_type'] ?? $template->business_type;
            $template->remark = $data['remark'] ?? $template->remark;
            $template->save();

            if (array_key_exists('nodes', $data)) {
                $this->deleteNodes($template->id);
                $this->writeNodes($template->id, $data['nodes']);
            }

            return $this->ResponseSucess(['id' => $template->id]);
        } catch (RepositoryException $e) {
            return $this->ResponseFail($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 启用/禁用
     */
    public function status(Request $request)
    {
        try {
            $template = $this->templateRepository->findById($request->input('id'));
            if (empty($template)) {
                throw new RepositoryException('审批流程不存在', 10001);
            }
            $template->status = $request->input('status');
            $template->save();
            return $this->ResponseSucess(['id' => $template->id]);
        } catch (RepositoryException $e) {
            return $this->ResponseFail($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 删除：模板逻辑删除，配置子表物理级联删除（配置类数据无需保留删除历史，§12.1）
     */
    public function del(Request $request)
    {
        try {
            $template = $this->templateRepository->findById($request->input('id'));
            if (empty($template)) {
                throw new RepositoryException('审批流程不存在', 10001);
            }
            $this->deleteNodes($template->id);
            $template->is_deleted = 1;
            $template->save();
            return $this->ResponseSucess(['id' => $template->id]);
        } catch (RepositoryException $e) {
            return $this->ResponseFail($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 按请求体 nodes 数组写入节点+执行人+字段，数组下标即 sort_order（§12.1）
     */
    protected function writeNodes(int $templateId, array $nodes): void
    {
        foreach (array_values($nodes) as $sortOrder => $nodeData) {
            $node = $this->nodeRepository->create([
                'template_id'     => $templateId,
                'node_name'       => $nodeData['node_name'],
                'node_type'       => $nodeData['node_type'],
                // 枚举代码见 prd/sql/oa_dict.json：approval=审批节点，all_approve=会签，forbidden=禁止编辑，default_open=默认开放
                'approval_rule'   => $nodeData['node_type'] === 'approval' ? ($nodeData['approval_rule'] ?? 'all_approve') : null,
                'field_edit_mode' => $nodeData['field_edit_mode'] ?? ($nodeData['node_type'] === 'approval' ? 'forbidden' : 'default_open'),
                'sort_order'      => $sortOrder,
            ]);

            foreach ($nodeData['executors'] ?? [] as $execSort => $exec) {
                $this->executorRepository->create([
                    'node_id'        => $node->id,
                    'exec_type'      => $exec['exec_type'],
                    'exec_target_id' => $exec['exec_target_id'] ?? null,
                    'sort_order'     => $execSort,
                ]);
            }

            foreach ($nodeData['fields'] ?? [] as $fieldSort => $field) {
                $this->fieldRepository->create([
                    'node_id'      => $node->id,
                    'field_name'   => $field['field_name'],
                    'field_key'    => $field['field_key'],
                    'field_type'   => $field['field_type'],
                    'is_required'  => $field['is_required'] ?? false,
                    'default_rule' => $field['default_rule'] ?? null,
                    'sort_order'   => $fieldSort,
                ]);
            }
        }
    }

    protected function deleteNodes(int $templateId): void
    {
        $nodeIds = $this->nodeRepository->where('template_id', $templateId)->pluck('id');
        $this->executorRepository->whereIn('node_id', $nodeIds)->delete();
        $this->fieldRepository->whereIn('node_id', $nodeIds)->delete();
        $this->nodeRepository->where('template_id', $templateId)->delete();
    }
}
