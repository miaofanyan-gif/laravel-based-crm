<?php
namespace JoyMedia\Toutiao\Traits;
trait ToutiaoRedisKeysTrait
{
    /**
     * auth code redisKey
     * @param $user_id
     * @return string
     */
    public function getToutiaoCodeKey($user_id=1){
        return 'auth_code_toutiao_v2_' . $user_id;
    }
    /**
     * token redisKey
     * @param $app_id
     * @return string
     */
    public function getToutiaoTokenKey($app_id){
        return 'tt_toutiao_token_' . $app_id;
    }

    /**
     * tokenDetails redisKey
     * @param $app_id
     * @return string
     */
    public function getTokenDetailsKey($app_id){
        return 'tt_toutiao_detail_' . $app_id;
    }

    /**
     * 广告主排名 redisKey
     * @param $advertiser_id
     * @return string
     */
    public function getAdRankingRedisKey()
    {
        return 'tt_advertiser_ranking';
    }

    /**
     * 同步状态
     */
    public function getSyncStatusRedisKey($oauth_token_id)
    {
        return 'toutiao_sync_advertiser_status_'.$oauth_token_id;
    }

    /**
     * 千川广告主排名 redisKey
     * @return string
     */
    public function getQianchuanAdRankingRedisKey()
    {
        return 'qianchuan_advertiser_ranking';
    }

    /**
     * 千川同步状态
     */
    public function getQianchuanSyncStatusRedisKey($oauth_token_id)
    {
        return 'qianchuan_sync_advertiser_status_'.$oauth_token_id;
    }
    /**
     * 共享钱包
     */
    public function account_wallet_id_redis_key()
    {
        return 'agent_wallet_id_more_redis_keys';
    }

    public function get_app_access_token_redis_key($app_id)
    {
        return 'app_access_token_redis_keys:'.$app_id;
    }
}
