<?php

namespace JoyCrm\OaApproval\Server;

use Common\Exceptions\RepositoryException;
use Common\Permissions\Models\RoleUser;
use Common\Permissions\Models\User;
use Illuminate\Support\Facades\Log;
use JoyCrm\OaApproval\Model\ApprovalRecord;
use JoyCrm\OaApproval\Model\ApprovalTask;
use JoyCrm\OaApproval\Model\FlowNode;
use JoyCrm\OaApproval\Repositories\ApprovalFieldChangeRepository;
use JoyCrm\OaApproval\Repositories\ApprovalRecordExecutorRepository;
use JoyCrm\OaApproval\Repositories\ApprovalRecordRepository;
use JoyCrm\OaApproval\Repositories\ApprovalTaskRepository;
use JoyCrm\OaApproval\Repositories\FlowNodeFieldRepository;
use JoyCrm\OaApproval\Repositories\FlowNodeRepository;
use JoyCrm\OaApproval\Repositories\FlowTemplateRepository;

/**
 * OA 审批状态机核心逻辑（跨模块共享能力，OA审批.prd.md §十三 集成契约的后端实现）
 *
 * 方法命名对应 PRD §12.2 的接口语义：submit/approve/reject/returnTo/complete/withdraw，
 * 业务模块统一调用这几个方法，不允许各自实现节点推进逻辑（§模块概述"强制约束"）。
 * 本类只处理状态机，不做 HTTP 相关处理，成功返回数组，失败抛 RepositoryException，
 * 由调用方（业务模块 Controller 或本包 ApprovalTaskController）自行包 ResponseSucess/ResponseFail。
 *
 * 枚举值一律用 prd/sql/oa_dict.json 里登记的英文代码（数据库不存中文），
 * 比如 node_type=approval/process/submitter、result=pending/agreed/rejected/completed 等，
 * 这份字典是权威来源，跟 PRD 正文的中文描述一一对应，新增分支前先查字典别自己编代码。
 */
class ApprovalEngine
{
    protected $templateRepository;
    protected $nodeRepository;
    protected $nodeFieldRepository;
    protected $taskRepository;
    protected $recordRepository;
    protected $recordExecutorRepository;
    protected $fieldChangeRepository;

    public function __construct(
        FlowTemplateRepository $templateRepository,
        FlowNodeRepository $nodeRepository,
        FlowNodeFieldRepository $nodeFieldRepository,
        ApprovalTaskRepository $taskRepository,
        ApprovalRecordRepository $recordRepository,
        ApprovalRecordExecutorRepository $recordExecutorRepository,
        ApprovalFieldChangeRepository $fieldChangeRepository
    ) {
        $this->templateRepository = $templateRepository;
        $this->nodeRepository = $nodeRepository;
        $this->nodeFieldRepository = $nodeFieldRepository;
        $this->taskRepository = $taskRepository;
        $this->recordRepository = $recordRepository;
        $this->recordExecutorRepository = $recordExecutorRepository;
        $this->fieldChangeRepository = $fieldChangeRepository;
    }

    /**
     * 发起审批（§13.1：业务单据由"草稿"转"已提交"时调用）
     *
     * @return array{task_id:int, current_node_name:?string, current_approver_id:?int, current_approver_ids:int[], task_status:string}
     */
    public function submit(string $businessType, $businessId, string $businessNo, int $submitterId, ?int $templateId = null): array
    {
        // 幂等：同 business_type+business_no 已存在任务，直接返回已有 task_id（§13.1 幂等与失败处理）
        $exists = $this->taskRepository->findByBusinessNo($businessType, $businessNo);
        if (!empty($exists)) {
            return $this->taskBrief($exists);
        }

        $template = $templateId
            ? $this->templateRepository->findById($templateId)
            : $this->templateRepository->findEnabledByBusinessType($businessType);
        if (empty($template) || $template->status !== 'enabled') {
            throw new RepositoryException('审批流程不存在或未启用', 10001);
        }

        $submitter = User::find($submitterId);
        if (empty($submitter)) {
            throw new RepositoryException('提交人不存在', 10001);
        }

        $task = $this->taskRepository->create([
            'template_id'   => $template->id,
            'business_type' => $businessType,
            'business_id'   => $businessId,
            'business_no'   => $businessNo,
            'submitter_id'  => $submitterId,
            'submit_time'   => now(),
            'status'        => 'in_progress', // 首个真实节点确定后会被 advanceOrFinish 覆盖为真实状态
            'department'    => $submitter->department_name ?? '',
        ]);

        // 发起人虚拟节点（§11.1 关键设计说明 3：sort_order=-1，天然排在所有真实节点之前）
        $this->recordRepository->create([
            'task_id'    => $task->id,
            'node_name'  => '发起人',
            'node_type'  => 'submitter',
            'result'     => 'submitted',
            'start_time' => now(),
            'finish_time'=> now(),
            'sort_order' => -1,
        ]);

        $firstNode = $this->nodeRepository->findByTemplateAndSort($template->id, 0);
        if (empty($firstNode)) {
            // 流程没配节点，视为直接完结
            $task->status = 'completed';
            $task->current_node_name = null;
            $task->current_approver_id = null;
            $task->save();
        } else {
            $this->createNodeRecord($task, $firstNode);
        }

        return $this->taskBrief($task->fresh());
    }

    /**
     * 同意（仅审批节点）
     */
    public function approve(int $taskId, int $executorId, ?string $comment = null, array $fieldValues = []): array
    {
        $task = $this->taskRepository->findOrFail($taskId);
        $this->assertNotTerminal($task);

        $record = $this->recordRepository->currentActive($taskId);
        if (empty($record) || $record->node_type !== 'approval') {
            throw new RepositoryException('当前节点不是审批节点，无法执行同意操作', 10002);
        }

        $executorRow = $this->recordExecutorRepository->findPending($record->id, $executorId);
        if (empty($executorRow)) {
            throw new RepositoryException('未找到您在当前节点的待办，可能已处理过或不是当前审批人', 10003);
        }

        $node = $this->currentNodeConfig($task, $record);
        $this->validateRequiredFields($node, $fieldValues);
        $fieldChanges = $this->recordFieldChanges($record, $node, $fieldValues, $executorId);

        $executorRow->result = 'agreed';
        $executorRow->operate_time = now();
        $executorRow->comment = $comment;
        $executorRow->save();

        $passed = $this->settleAfterAction($record, $executorId, 'agreed', $record->approval_rule);

        if ($passed) {
            $record->result = 'approved';
            $record->finish_time = now();
            $record->save();
            $this->advanceOrFinish($task, $record);
        }

        return $this->taskBrief($task->fresh()) + ['field_changes' => $fieldChanges];
    }

    /**
     * 驳回（仅审批节点）
     */
    public function reject(int $taskId, int $executorId, ?string $comment = null): array
    {
        $task = $this->taskRepository->findOrFail($taskId);
        $this->assertNotTerminal($task);

        $record = $this->recordRepository->currentActive($taskId);
        if (empty($record) || $record->node_type !== 'approval') {
            throw new RepositoryException('当前节点不是审批节点，无法执行驳回操作', 10002);
        }

        $executorRow = $this->recordExecutorRepository->findPending($record->id, $executorId);
        if (empty($executorRow)) {
            throw new RepositoryException('未找到您在当前节点的待办，可能已处理过或不是当前审批人', 10003);
        }

        $executorRow->result = 'rejected';
        $executorRow->operate_time = now();
        $executorRow->comment = $comment;
        $executorRow->save();

        $rejected = $this->settleAfterAction($record, $executorId, 'rejected', $record->approval_rule);

        if ($rejected) {
            $record->result = 'rejected';
            $record->finish_time = now();
            $record->save();
            $this->terminateAsRejected($task);
        }

        return $this->taskBrief($task->fresh());
    }

    /**
     * 退回（§五：退回上一节点 或 直接退回提交人）
     */
    public function returnTo(int $taskId, int $executorId, string $target, ?string $comment = null): array
    {
        $task = $this->taskRepository->findOrFail($taskId);
        $this->assertNotTerminal($task);

        $record = $this->recordRepository->currentActive($taskId);
        if (empty($record) || $record->node_type !== 'approval') {
            throw new RepositoryException('当前节点不是审批节点，无法执行退回操作', 10002);
        }

        $executorRow = $this->recordExecutorRepository->findPending($record->id, $executorId);
        if (empty($executorRow)) {
            throw new RepositoryException('未找到您在当前节点的待办，可能已处理过或不是当前审批人', 10003);
        }

        $executorRow->result = 'rejected';
        $executorRow->operate_time = now();
        $executorRow->comment = $comment;
        $executorRow->save();
        // 退回视为该节点当次终止，并发拦截同驳回场景
        $this->recordExecutorRepository->closeRestAsDone($record->id, $executorId);

        $record->result = 'rejected';
        $record->finish_time = now();
        $record->save();

        $targetSortOrder = $record->sort_order - 1;

        if ($target === '提交人' || $targetSortOrder < 0) {
            // 退提交人 = 流程终止，提交人需重新发起一次全新任务（§5.1）
            $this->terminateAsRejected($task);
            return $this->taskBrief($task->fresh());
        }

        // 退回上一节点：新建一条记录，而不是重开旧记录（§5.1，保留历史意见不被覆盖）
        $prevRecord = $this->recordRepository->latestAtSortOrder($taskId, $targetSortOrder);
        if (empty($prevRecord)) {
            // 理论上不会发生：流程既然走到当前节点，必然经过上一节点
            throw new RepositoryException('无法定位上一节点的历史记录，请联系管理员', 10004);
        }

        $newRecord = $this->recordRepository->create([
            'task_id'      => $task->id,
            'node_name'    => $prevRecord->node_name,
            'node_type'    => $prevRecord->node_type,
            'approval_rule'=> $prevRecord->approval_rule,
            'result'       => $prevRecord->node_type === 'approval' ? 'in_progress' : 'pending',
            'start_time'   => now(),
            'sort_order'   => $targetSortOrder,
        ]);

        $executorIds = [];
        foreach ($prevRecord->executors as $oldExecutor) {
            $this->recordExecutorRepository->create([
                'record_id'   => $newRecord->id,
                'executor_id' => $oldExecutor->executor_id,
                'result'      => 'pending',
            ]);
            $executorIds[] = $oldExecutor->executor_id;
        }

        $task->current_node_name = $newRecord->node_name;
        $task->current_approver_id = $executorIds[0] ?? null;
        $task->status = $newRecord->result;
        $task->save();

        return $this->taskBrief($task->fresh());
    }

    /**
     * 办结（仅处理节点，任一执行人办结即完成，§2.1）
     */
    public function complete(int $taskId, int $executorId, ?string $comment = null, array $fieldValues = []): array
    {
        $task = $this->taskRepository->findOrFail($taskId);
        $this->assertNotTerminal($task);

        $record = $this->recordRepository->currentActive($taskId);
        if (empty($record) || $record->node_type !== 'process') {
            throw new RepositoryException('当前节点不是处理节点，无法执行办结操作', 10002);
        }

        $executorRow = $this->recordExecutorRepository->findPending($record->id, $executorId);
        if (empty($executorRow)) {
            throw new RepositoryException('未找到您在当前节点的待办，可能已处理过或不是当前处理人', 10003);
        }

        $node = $this->currentNodeConfig($task, $record);
        $this->validateRequiredFields($node, $fieldValues);
        $fieldChanges = $this->recordFieldChanges($record, $node, $fieldValues, $executorId);

        $executorRow->result = 'completed';
        $executorRow->operate_time = now();
        $executorRow->comment = $comment;
        $executorRow->save();
        $this->recordExecutorRepository->closeRestAsDone($record->id, $executorId);

        $record->result = 'completed';
        $record->finish_time = now();
        $record->save();

        $this->advanceOrFinish($task, $record);

        return $this->taskBrief($task->fresh()) + ['field_changes' => $fieldChanges];
    }

    /**
     * 撤回：仅当任务未进入实质审批（当前节点尚无任何执行人产生非"待处理"结果）时允许（§12.2）
     */
    public function withdraw(int $taskId, int $submitterId): bool
    {
        $task = $this->taskRepository->findOrFail($taskId);
        if ((int)$task->submitter_id !== $submitterId) {
            throw new RepositoryException('仅提交人本人可撤回', 10005);
        }
        if (!in_array($task->status, ['in_progress', 'pending'], true)) {
            throw new RepositoryException('当前状态不允许撤回', 10006);
        }

        $record = $this->recordRepository->currentActive($taskId);
        if (!empty($record)) {
            $acted = $record->executors()->where('result', '<>', 'pending')->exists();
            if ($acted) {
                throw new RepositoryException('当前节点已有人处理，无法撤回', 10006);
            }
        }

        $task->is_deleted = 1;
        $task->save();

        return true;
    }

    /**
     * 完整时间线（§7.3.2 流程进度可视化 / §7.3 审批详情页）
     */
    public function getTimeline(string $businessType, $businessId): array
    {
        $task = $this->taskRepository->findByBusiness($businessType, $businessId);
        if (empty($task)) {
            return ['task' => null, 'records' => []];
        }

        return [
            'task'    => $task,
            'records' => $this->recordRepository->timelineOf($task->id),
        ];
    }

    /**
     * 轻量状态查询（业务模块列表页"当前审批人"列用，不要为了展示一列自己拼时间线查询）
     */
    public function getTaskStatus(string $businessType, $businessId): ?array
    {
        $task = $this->taskRepository->findByBusiness($businessType, $businessId);
        if (empty($task)) {
            return null;
        }

        return $this->taskBrief($task);
    }

    // ------------------------------------------------------------------
    // 内部实现
    // ------------------------------------------------------------------

    protected function assertNotTerminal(ApprovalTask $task): void
    {
        if (in_array($task->status, ['completed', 'rejected', 'voided'], true) || $task->is_deleted) {
            throw new RepositoryException('审批已结束，禁止任何操作（终态锁定，§1.3）', 10007);
        }
    }

    /**
     * 会签/或签并发结算（§三）：返回 true 表示节点本次动作应当终结（通过 或 驳回）
     */
    protected function settleAfterAction(ApprovalRecord $record, int $executorId, string $actionResult, ?string $rule): bool
    {
        if ($actionResult === 'agreed') {
            if ($rule === 'any_approve') {
                // 或签：任意同意 -> 立即办结，锁定其余人
                $this->recordExecutorRepository->closeRestAsDone($record->id, $executorId);
                return true;
            }
            // 会签：全部同意才算通过
            $stillWaitingOrRejected = $record->executors()
                ->whereIn('result', ['pending', 'rejected'])
                ->exists();
            return !$stillWaitingOrRejected;
        }

        // $actionResult === 'rejected'
        if ($rule === 'all_approve') {
            // 会签：任意一人驳回 -> 立即终止，锁定其余人
            $this->recordExecutorRepository->closeRestAsDone($record->id, $executorId);
            return true;
        }
        // 或签：必须全员驳回才终止节点
        $notRejected = $record->executors()->where('result', '<>', 'rejected')->exists();
        return !$notRejected;
    }

    protected function terminateAsRejected(ApprovalTask $task): void
    {
        $task->status = 'rejected';
        $task->current_node_name = null;
        $task->current_approver_id = null;
        $task->save();
    }

    /**
     * 节点办结后推进到下一节点，或流程走完置为已完结
     */
    protected function advanceOrFinish(ApprovalTask $task, ApprovalRecord $finishedRecord): void
    {
        $nextNode = $this->nodeRepository->findByTemplateAndSort($task->template_id, $finishedRecord->sort_order + 1);
        if (empty($nextNode)) {
            $task->status = 'completed';
            $task->current_node_name = null;
            $task->current_approver_id = null;
            $task->save();
            return;
        }
        $this->createNodeRecord($task, $nextNode);
    }

    /**
     * 为某个节点创建审批记录 + 执行人明细，并同步冗余到任务表
     */
    protected function createNodeRecord(ApprovalTask $task, FlowNode $node): void
    {
        $executorIds = $this->resolveExecutors($node, (int)$task->submitter_id);
        if (empty($executorIds)) {
            throw new RepositoryException("节点【{$node->node_name}】未能解析出有效执行人，请检查审批流配置", 10008);
        }

        $result = $node->node_type === 'approval' ? 'in_progress' : 'pending';

        $record = $this->recordRepository->create([
            'task_id'      => $task->id,
            'node_name'    => $node->node_name,
            'node_type'    => $node->node_type,
            'approval_rule'=> $node->approval_rule,
            'result'       => $result,
            'start_time'   => now(),
            'sort_order'   => $node->sort_order,
        ]);

        foreach ($executorIds as $executorId) {
            $this->recordExecutorRepository->create([
                'record_id'   => $record->id,
                'executor_id' => $executorId,
                'result'      => 'pending',
            ]);
        }

        $task->current_node_name = $node->node_name;
        // 会签/或签多人场景，oa_approval_task.current_approver_id 只缓存"主要处理人"（第一个），
        // 完整多人状态以 oa_approval_record_executor 为准（迁移文件注释里的既定设计）
        $task->current_approver_id = $executorIds[0];
        $task->status = $result;
        $task->save();
    }

    /**
     * 三类执行人规则混合解析（§2.2），按 user id 去重，返回 user id 列表
     * （本表不冗余存姓名，展示时联查 Common\Permissions\Models\User）
     *
     * @return int[]
     */
    protected function resolveExecutors(FlowNode $node, int $submitterId): array
    {
        $resolved = [];

        foreach ($node->executors as $config) {
            switch ($config->exec_type) {
                case 'specific_user':
                    $user = User::find($config->exec_target_id);
                    if ($user) {
                        $resolved[$user->id] = true;
                    }
                    break;

                case 'specific_role':
                    $userIds = RoleUser::where('role_id', $config->exec_target_id)->pluck('user_id');
                    foreach ($userIds as $userId) {
                        if (User::find($userId)) {
                            $resolved[$userId] = true;
                        }
                    }
                    break;

                case 'supervisor':
                    $submitter = User::find($submitterId);
                    $leaderId = $submitter->competentInfo['id'] ?? null;
                    if ($leaderId && User::find($leaderId)) {
                        $resolved[$leaderId] = true;
                    } else {
                        Log::channel('oa_approval')->warning('直属上级解析失败', [
                            'submitter_id' => $submitterId,
                            'node_id'      => $node->id,
                        ]);
                    }
                    break;
            }
        }

        return array_keys($resolved);
    }

    /**
     * 反查记录快照对应的节点配置（record 只存 node_name 快照，不存 node_id，见 §11.7 说明）
     */
    protected function currentNodeConfig(ApprovalTask $task, ApprovalRecord $record): ?FlowNode
    {
        return $this->nodeRepository->findByTemplateAndName($task->template_id, $record->node_name);
    }

    protected function validateRequiredFields(?FlowNode $node, array $fieldValues): void
    {
        if (empty($node)) {
            return;
        }
        $requiredKeys = $this->nodeFieldRepository->requiredKeysOf($node->id);
        $missing = [];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $fieldValues) || $fieldValues[$key] === '' || is_null($fieldValues[$key])) {
                $missing[] = $key;
            }
        }
        if (!empty($missing)) {
            throw new RepositoryException('缺少必填字段：' . implode(',', $missing), 400);
        }
    }

    /**
     * 落库字段修改留痕（§11.9），并返回给调用方用于业务模块回写自身字段（§13.1 field_changes）
     */
    protected function recordFieldChanges(ApprovalRecord $record, ?FlowNode $node, array $fieldValues, int $operatorId): array
    {
        if (empty($fieldValues)) {
            return [];
        }

        $fieldConfigs = $node ? $node->fields->keyBy('field_key') : collect();
        $changes = [];

        foreach ($fieldValues as $key => $value) {
            $fieldName = $fieldConfigs->get($key)->field_name ?? $key;

            $this->fieldChangeRepository->create([
                'record_id'    => $record->id,
                'field_key'    => $key,
                'field_name'   => $fieldName,
                'old_value'    => null,
                'new_value'    => is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE),
                'operator_id'  => $operatorId,
                'operate_time' => now(),
            ]);

            $changes[] = ['field_key' => $key, 'field_name' => $fieldName, 'new_value' => $value];
        }

        return $changes;
    }

    protected function taskBrief(ApprovalTask $task): array
    {
        $currentApproverIds = [];
        $record = $this->recordRepository->currentActive($task->id);
        if ($record) {
            $currentApproverIds = $record->executors()->where('result', 'pending')->pluck('executor_id')->toArray();
        }

        return [
            'task_id'               => $task->id,
            'current_node_name'     => $task->current_node_name,
            'current_approver_id'   => $task->current_approver_id,
            'current_approver_ids'  => $currentApproverIds,
            'task_status'           => $task->status,
        ];
    }
}
