<?php

namespace JoyCrm\OaApproval\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array submit(string $businessType, $businessId, string $businessNo, int $submitterId, ?int $templateId = null)
 * @method static array approve(int $taskId, int $executorId, ?string $comment = null, array $fieldValues = [])
 * @method static array reject(int $taskId, int $executorId, ?string $comment = null)
 * @method static array returnTo(int $taskId, int $executorId, string $target, ?string $comment = null)
 * @method static array complete(int $taskId, int $executorId, ?string $comment = null, array $fieldValues = [])
 * @method static bool withdraw(int $taskId, int $submitterId)
 * @method static array getTimeline(string $businessType, $businessId)
 * @method static array|null getTaskStatus(string $businessType, $businessId)
 */
class OaApproval extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'JoyCrmOaApproval';
    }
}
