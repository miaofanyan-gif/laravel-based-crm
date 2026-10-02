<?php
/**
 *头条广告主信息
 *
 */

namespace JoyMedia\Qianchuan\Jobs;
use Log;
use App\Jobs\Job;
use JoyGc\BotRecharge\Facades\AgentAdvertiser;
use JoyMedia\Qianchuan\Server\AgentOauth;

use JoyMedia\Qianchuan\Server\Media;
class AgentAdvertiserInfoJob extends Job
{
    public $advertiser_ids;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($advertiser_ids)
    {
        $this->advertiser_ids = $advertiser_ids;//要查询的广告主
    }

    /**
     * Execute the job.
     *
     * @return void
     *
     */
    public function handle()
    {
        try{
            $media = new Media((new AgentOauth())->getAgentToken());
            $result = $media->getAdvertiserInfoQuery($this->advertiser_ids);
            if(!empty($result['account_detail_list'])){
                Log::channel('qianchuan_advertiser')->info('广告主投放信息：',[count($result['account_detail_list'])]);
                AgentAdvertiser::saveAdvertiser($result['account_detail_list'],config('config.advertiser_type.AD_ADVERTISER_TYPE'));
            }
        } catch ( \Exception $e){
            Log::channel('qianchuan_advertiser')->error('广告主信息同步错误00002：'.$e->getMessage().'文件：'.$e->getFile().'行数：'.$e->getLine());
        }
    }
}
