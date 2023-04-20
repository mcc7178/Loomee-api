<?php

namespace App\Process;

use App\Foundation\Facades\Log;
use App\Model\Product\Product;
use App\Utils\Redis;
use Hyperf\Process\AbstractProcess;

class FloorPriceProcess extends AbstractProcess
{
    public function handle(): void
    {
        $redis = Redis::getInstance();
        $cid = $redis->rPop('floorPriceProcess');
        if ($cid) {
            $prices = Product::query()->where(['collection_id' => $cid, 'status' => 1])->pluck('price')->toArray();
            $min = min($prices);
            $avg = rtrim(sprintf('%.8f', bcdiv(array_sum($prices), count($prices), 8)), '0');
            $minPrice = $min == 0 ? 0 : rtrim(sprintf('%.8f', $min), '0');
            $redis->hSet('floor_price', "collection_$cid:" . date('Y-m-d'), $minPrice);
            $redis->hSet('floor_price', "collection_$cid", $minPrice);
            $redis->hSet('avg_price', "collection_$cid:" . date('Y-m-d'), $avg);
            $redis->hSet('avg_price', "collection_$cid", $avg);
            Log::codeDebug()->info("合集:$cid,地板价:$minPrice,均价:$avg");
        }
    }
}