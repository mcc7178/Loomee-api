<?php

namespace App\Task;

use App\Foundation\Facades\Log;
use App\Service\NftReptileService;
use App\Utils\Redis;

class NftTransferTask
{
    public function handle()
    {
        $redis = Redis::getInstance();
        $data = $redis->rPop('nftTransfer');
        if ($data) {
            Log::codeDebug()->info("nftTransfer:$data");
            $data = json_decode($data, true);
            (new NftReptileService())->refresh($data['id'], $data['contract'], $data['tokenID']);
        }
    }
}