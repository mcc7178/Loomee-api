<?php
declare(strict_types=1);

namespace App\Process;

use Hyperf\Utils\ApplicationContext;
use App\Constants\RedisCode;

/**
 * redis 任务投递
 * Class NftTaskProcess
 * @package App\Process
 * @Author hy
 * @Date: 2021/4/23
 */
class NftTaskProcess
{
    
    /**
     * nft 爬虫任务投递
     *
     * @param array $data
     * @return void
     */
    public function nftReptile(array $data = []): void
    {
        if(!empty($data))
        {
            $container = ApplicationContext::getContainer();
            $redis = $container->get(\Redis::class);

            $redis->lpush(RedisCode::REPTILE_TASK,json_encode($data));
        }
    }

  
    


}