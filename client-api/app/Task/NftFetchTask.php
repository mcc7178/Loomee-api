<?php

namespace App\Task;

use App\Constants\Common;
use App\Constants\RedisKey;
use App\Foundation\Facades\Log;
use App\Service\NftReptileService;
use App\Utils\Redis;

class NftFetchTask
{
    public function handle()
    {
        $redis = Redis::getInstance();
        $key = RedisKey::FETCH_NFT_BY_ADDRESS;
        $data = $redis->rPop($key);
        if ($data) {
            $data = json_decode($data, true);
            $address = $data['address'] ?? '';

            return $this->fetch($address);
        }
        //$redis->lPush($key, json_encode(['address' => '0xed48A3B8D7261143Dd52c505bAf318A2CdDC14C2']));
    }

    private function fetch($address)
    {
        if (strtolower(env('APP_ENV')) == 'dev') {
            $url = Common::TEST_ETH_API;              //测试
        } else {
            $url = Common::PRODUCT_ETH_API;              //正式
        }
        if ($address) {
            $apiKey = Common::ETH_API_TOKEN;
            $param = "module=account&action=tokennfttx&address=$address&page=1&offset=10000&startblock=0&endblock=latest&sort=asc&apikey=$apiKey";
            Log::codeDebug()->info(__METHOD__ . "url:" . $url . '?' . $param);
            $result = curl_get("$url?$param");
            Log::codeDebug()->info("result::-->status:{$result['status']},message:{$result['message']}");
            if ($result && isset($result['status']) && $result['status'] == 1) {
                $data = $result['result'];
                $step1 = [];
                foreach ($data as $item) {
                    if ($item['to'] == strtolower($address)) {
                        $step1[$item['contractAddress'] . '_' . $item['from'] . '_' . $item['to'] . '_' . $item['tokenID']][] = $item;
                    }
                }
                Log::codeDebug()->info("count1:" . count($step1));
                foreach ($step1 as $item) {
                    if (count($item) % 2 == 0) {
                        unset($step1[$item[0]['contractAddress'] . '_' . $item[0]['from'] . '_' . $item[0]['to'] . '_' . $item[0]['tokenID']]);
                    }
                }
                $nftReptileService = new NftReptileService();
                Log::codeDebug()->info("count2:" . count($step1));
                foreach ($step1 as $item) {
                    foreach ($item as $v) {
                        go(function () use ($v, $nftReptileService) {
                            $nftReptileService->handleNftData($v, $v['contractAddress']);
                        });
                        sleep(1);
                    }
                }
            }
        }
        return true;
    }
}