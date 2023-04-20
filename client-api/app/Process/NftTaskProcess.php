<?php

namespace App\Process;

use App\Constants\RedisKey;
use App\Foundation\Facades\Log;
use App\Service\NftReptileService;
use App\Utils\Redis;
use Hyperf\Process\AbstractProcess;

class NftTaskProcess extends AbstractProcess
{
    public function handle(): void
    {
        $key = RedisKey::NFT_REPTILE;
        $redis = Redis::getInstance();
        while (true) {
            $data = $redis->rpop($key);
            if ($data) {
                Log::codeDebug()->info("抓取NFT数据start111," . $data);
                go(function () use ($data) {
                    (new NftReptileService())->handle($data);
                });
                Log::codeDebug()->info("抓取NFT数据end," . $data);
            }
            sleep(1);
        }
    }
}