<?php

namespace App\Task;

use App\Foundation\Facades\Log;
use App\Model\Product\Product;
use App\Model\Product\ProductDynamic;
use App\Model\Product\ProductOffer;

class CancelOfferTask
{
    public function handle()
    {
        $date = date('Y-m-d H:i:s');
        $list = ProductOffer::query()
            ->select(['id', 'product_id', 'user_id', 'from'])
            ->where('status', 1)
            ->whereDate('expired_at', '<=', $date)
            ->get()
            ->toArray();
        if ($list) {
            $ids = array_column($list, 'id');
            ProductOffer::query()->whereIn('id', $ids)->update([
                'status' => 3,
                'updated_at' => $date
            ]);
            Log::codeDebug()->info('取消报价：' . implode('.', $ids));
        }

        $list2 = Product::query()
            ->where('status', 1)
            ->whereDate('expired_at', '<=', $date)
            ->get()
            ->toArray();
        if ($list2) {
            foreach ($list2 as $item) {
                Product::query()->where('id', $item['id'])->update(['status' => 0, 'updated_at' => $date]);
                $id = ProductDynamic::insertGetId([
                    'product_id' => $item['product_id'],
                    'collection_id' => $item['collection_id'],
                    'event' => 7,
                    'comefrom' => $item['owner'],
                    'reach' => '',
                    'coin_id' => $item['coin_id'],
                    'coin_token' => '',
                    'price' => $item['price'],
                    'created_at' => $date,
                    'updated_at' => $date
                ]);
                Log::codeDebug()->info("超时下架，新增动态:$id");
            }
        }
    }
}