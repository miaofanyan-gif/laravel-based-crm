<?php

namespace JoyCrm\OaApproval\Http\Controllers;

use Common\Bootstrap\BaseController;
use Common\Exceptions\RepositoryException;
use Illuminate\Http\Request;
use JoyCrm\OaApproval\Repositories\ApprovalTaskRepository;
use JoyCrm\OaApproval\Server\ApprovalEngine;

/**
 * 审批任务（用户操作端，OA审批.prd.md §七 / §12.2）
 */
class ApprovalTaskController extends BaseController
{
    protected $taskRepository;
    protected $engine;

    // 提交动作里，除了这些保留字段，其余全部当作节点可编辑字段的新值（§12.2 approve/complete）
    protected $reservedKeys = ['id', 'comment', 'target'];

    public function __construct(ApprovalTaskRepository $taskRepository, ApprovalEngine $engine)
    {
        $this->taskRepository = $taskRepository;
        $this->engine = $engine;
        parent::__construct($taskRepository);
    }

    /**
     * 待我审批列表（§7.1）
     */
    public function pending(Request $request)
    {
        $result = $this->taskRepository->pendingForUser(auth()->id(), (int)$request->input('size', 15));
        return $this->ResponseSucess($result);
    }

    /**
     * 我发起的列表（§7.2）
     */
    public function mine(Request $request)
    {
        $request->merge(['submitter_id' => auth()->id(), 'is_deleted' => 0]);
        return $this->ResponseSucess($this->list($request));
    }

    /**
     * 全部审批列表（管理视角）
     */
    public function all(Request $request)
    {
        $request->merge(['is_deleted' => 0]);
        return $this->ResponseSucess($this->list($request));
    }

    /**
     * 审批详情（含完整时间线，§7.3）
     */
    public function detail(Request $request)
    {
        try {
            $task = $this->taskRepository->findOrFail($request->input('id'));
            $timeline = $this->engine->getTimeline($task->business_type, $task->business_id);
            return $this->ResponseSucess($timeline);
        } catch (RepositoryException $e) {
            return $this->ResponseFail($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 开放接口：供业务模块发起审批调用（§13.1）
     */
    public function submit(Request $request)
    {
        try {
            $result = $this->engine->submit(
                $request->input('business_type'),
                $request->input('business_id'),
                $request->input('business_no'),
                $request->input('submitter_id', auth()->id()),
                $request->input('template_id')
            );
            return $this->ResponseSucess($result);
        } catch (RepositoryException $e) {
            return $this->ResponseFail($e->getCode(), $e->getMessage());
        }
    }

    public function approve(Request $request)
    {
        try {
            $result = $this->engine->approve(
                $request->input('id'),
                auth()->id(),
                $request->input('comment'),
                $request->except($this->reservedKeys)
            );
            return $this->ResponseSucess($result);
        } catch (RepositoryException $e) {
            return $this->ResponseFail($e->getCode(), $e->getMessage());
        }
    }

    public function reject(Request $request)
    {
        try {
            $result = $this->engine->reject($request->input('id'), auth()->id(), $request->input('comment'));
            return $this->ResponseSucess($result);
        } catch (RepositoryException $e) {
            return $this->ResponseFail($e->getCode(), $e->getMessage());
        }
    }

    public function return(Request $request)
    {
        try {
            $result = $this->engine->returnTo(
                $request->input('id'),
                auth()->id(),
                $request->input('target'),
                $request->input('comment')
            );
            return $this->ResponseSucess($result);
        } catch (RepositoryException $e) {
            return $this->ResponseFail($e->getCode(), $e->getMessage());
        }
    }

    public function complete(Request $request)
    {
        try {
            $result = $this->engine->complete(
                $request->input('id'),
                auth()->id(),
                $request->input('comment'),
                $request->except($this->reservedKeys)
            );
            return $this->ResponseSucess($result);
        } catch (RepositoryException $e) {
            return $this->ResponseFail($e->getCode(), $e->getMessage());
        }
    }

    public function withdraw(Request $request)
    {
        try {
            $this->engine->withdraw($request->input('id'), auth()->id());
            return $this->ResponseSucess([]);
        } catch (RepositoryException $e) {
            return $this->ResponseFail($e->getCode(), $e->getMessage());
        }
    }
}
