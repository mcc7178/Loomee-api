<?php


namespace App\Services;


use App\Lib\Redis;

class MineService
{
    public static function getMinePrice($mine_usdt_exchange, $price)
    {
        $coin = 'usdt';
        if (!$mine_usdt_exchange)
        {
            $coin = strtolower(env('PLATFORM_COIN'));
            $market = $coin.'/usdt';
            $marketTradeData = Redis::getInstance()->hGet('trade_market', $market);
            if ($marketTradeData)
            {
                $marketTradeData = json_decode($marketTradeData, true);
                $tradePrice = $marketTradeData['p'];
                $price = bcdiv($price, $tradePrice, 4);
            }
        }
        return [
            'coin' => $coin,
            'price' => $price
        ];
    }
}
