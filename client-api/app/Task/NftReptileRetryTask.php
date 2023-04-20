<?php

namespace App\Task;

use App\Constants\RedisKey;
use App\Foundation\Facades\Log;
use App\Service\NftReptileService;
use App\Utils\Redis;

class NftReptileRetryTask
{
    public function handle()
    {
        $key = RedisKey::NFT_REPTILE_RETRY;
        $redis = Redis::getInstance();
        while (true) {
            $data = $redis->rPop($key);
            if ($data) {
                go(function () use ($data) {
                    $result = json_decode($data, true);
                    var_dump($result);
                    Log::codeDebug()->info("重试," . $data);
                    (new NftReptileService())->refresh(0, $result['tokenID'], $result['contract']);
                });
            }
            sleep(1);
        }
    }
}