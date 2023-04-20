<?php

namespace App\Task;

use App\Service\Binance;
use App\Utils\Redis;

class BinancePriceTask
{
    /**
     * Notes:币价
     * User: Deycecep
     * DateTime: 2022/4/24 15:29
     */
    public function handle()
    {
        $redis = Redis::getInstance();
        $res = Binance::getInstance()->getPrice('ETHUSDT');
        $redis->hset('binance_price',$res['symbol'],$res['price']);
    }
}