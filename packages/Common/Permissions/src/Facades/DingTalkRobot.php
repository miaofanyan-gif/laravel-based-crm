<?php
namespace Common\Permissions\Facades;

use Illuminate\Support\Facades\Facade;

/**
 *
 * @see \Illuminate\Log\Logger
 */
class DingTalkRobot extends Facade{

    protected static function getFacadeAccessor()
    {
        return 'DingTalkRobot';
    }
    public static function withWebhook($robotKey)
    {
        return app('DingTalkRobot', [$robotKey]);
    }
}


