<?php
/**
 *头条广告主信息
 *
 */

namespace JoyMedia\Toutiao\Console;
use Illuminate\Console\Command;
use JoyMedia\Toutiao\Jobs\AgentAdvertiserInfoJob;
use Log;
use JoyMedia\Toutiao\Server\AgentOauth;
use Common\Libs\Common;

use JoyMedia\Toutiao\Server\Media;
class AgentAdvertiser extends Command
{
    protected $cursor=0;//游标
    /**
     * 控制台命令的名称和签名。
     *
     * @var string
     */
    protected $signature = 'tt-agent-advertiser-list {type=time}';

    /**
     * 此任务的描述
     *
     * @var string
     */
    protected $description = 'get toutiao agent advertiser ids list daily';

    protected Media $media;

    /**
     * 同步广告主数据
     */
    public function handle() {
        $type = $this->argument('type');
        Log::channel('toutiao_advertiser')->info('执行：tt-advertiser:send');
        $hour = (int)date('His');
        if ($hour >= 65900 &&  $hour <= 73500) {
            $type = 'day'; //全量
            Log::channel('toutiao_advertiser')->info('全量数据：' ,[$type]);
            if($hour >= 70900){
                return false;
            }
        }
        Log::channel('toutiao_advertiser')->info('全量数据：' ,[$type]);
        //头条代理商id获取
        $agent_lists = config('media.toutiao.agent_lists');
        $this->media = new Media((new AgentOauth())->getAgentToken());
        Log::channel('toutiao_advertiser')->info('代理商ID数据：' ,[$agent_lists]);
        collect($agent_lists)->pluck('agent_id')->each(function ($agent_id) use ($type) {
            $this->syncAdvertiserIds($agent_id,$type);
        });
    }

    /**
     * 不要怀疑为什么这么写，头条账户太多，用job可能会超时
     * @param $agent_id
     * @param $type
     * @return void
     */
    protected function syncAdvertiserIds($agent_id,$type = 0)
    {
        //查询代理商 每一百条去获取一次代理商信息。
        Log::channel('toutiao_advertiser')->info('代理商同步任务开始：' . $agent_id);
        $this->cursor =0;
        Common::foreachUnlimited(function ($page) use($agent_id,$type){
            try {
                //去头条获取广告主信息
                $data = $this->media->agentAdvertiserList($agent_id, $this->cursor,$type);//此处取值2000
                Log::channel('toutiao_advertiser')->info('代理商id：'.$agent_id.'广告主id：' ,[count($data['data'])]);
                if (isset($data['data']) && count($data['data']) > 0) {
                    $this->cursor = $data['cursor'];
//                    Common::printR(count($data));
                    collect($data['data'])->chunk(50)->map(function ($advertiser) use($agent_id){//接口限制是100个广告主
                        dispatch((new AgentAdvertiserInfoJob($advertiser->values()->toArray()))->onQueue('dd_toutiao'));
                    });
                    usleep(rand(2000000, 8000000));
                }
                return $data['is_end'];
            } catch (\Exception $e) {
                Log::channel('toutiao_advertiser')->error('代理商同步错误00001：' . $e->getMessage() . '文件：' . $e->getFile());
            }
            return false;
        });
        Log::channel('toutiao_advertiser')->info('代理商同步任务结束' . $agent_id);
    }


}
