<?php

namespace JoyCrm\OaApproval\Repositories;

use Common\Exceptions\RepositoryException;
use JoyCrm\OaApproval\Model\FlowTemplate;
use Prettus\Repository\Eloquent\BaseRepository;

class FlowTemplateRepository extends BaseRepository
{
    public function model()
    {
        return FlowTemplate::class;
    }

    /**
     * 按业务类型取当前启用的模板（发起审批/预览流程时用）
     */
    public function findEnabledByBusinessType(string $businessType): FlowTemplate
    {
        $template = $this->where('business_type', $businessType)
            ->where('status', 'enabled') // 枚举代码见 prd/sql/oa_dict.json：enabled=已启用
            ->where('is_deleted', 0)
            ->first();
        if (empty($template)) {
            throw new RepositoryException('该单据类型未配置审批流程，请先在审批流配置中新建', 10001);
        }
        return $template;
    }

    public function detailWithNodes($id)
    {
        $template = $this->with(['nodes', 'nodes.executors', 'nodes.fields'])->where('id', $id)->first();
        if (empty($template)) {
            throw new RepositoryException('审批流程不存在', 10001);
        }
        return $template;
    }

    /**
     * 按主键查找，找不到返回 null（注意：prettus 自带的 find() 内部走 findOrFail，会抛未捕获异常，
     * 不要在这个包里直接调用 find()，统一走这个方法，跟 BotRecharge/PolicyRepository 的既有约定一致）
     */
    public function findById($id): ?FlowTemplate
    {
        return $this->where('id', $id)->first();
    }
}
