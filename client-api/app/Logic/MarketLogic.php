<?php


namespace App\Logic;


use App\Exceptions\BaseException;
use App\Utils\Redis;
use App\Model\Markets;

class MarketLogic
{
    public static function getMarketList()
    {
        $redis = Redis::getInstance();
        $data = $redis->hGetAll('trade_market');
        foreach ($data as &$datum) {
            $datum = json_decode($datum);
        }
        return $data;
    }

    public static function getMarketInfo($market)
    {
        $redis = Redis::getInstance();
        $data = $redis->hGet('trade_market', $market);
        return json_decode($data, true);
    }

    public static function setMarketCache()
    {
        $redis = Redis::getInstance();
        $marketData = Markets::query()
            ->get();
        foreach ($marketData as $item) {
            list($symbol, $block) = explode('/', $item->name);
            $marketName = strtolower($symbol.$block);
            $marketDataCache[$marketName] = [
                'symbol' => $symbol,
                'block' => $block,
                'name' => $marketName,
                'exchange' => $item->reptile_status === 1 ? 'huobi' : '',
                'price_precision' => $item->decimals_price,
                'amount_precision' => $item->decimals_number,
                'currency_precision' => $item->decimals_currency,
            ];
        }
        return $redis->set('market_symbol',json_encode($marketDataCache));
    }


    public static function getMarketPrice($symbol)
    {
        $symbol = strtolower($symbol);
        if ($symbol == 'fill')
            return 1;

        if ($symbol == 'usdt')
        {
            $blockPrice = Redis::getInstance()->get('market_btc_eth_usdt_price');
            return json_decode($blockPrice, true)['usdt'];
        }
        $data = Redis::getInstance()->hGet('trade_market',$symbol.'/usdt');
        
        $data = json_decode($data,true);
        if (!empty($data) && isset($data['p'])){
            return $data['p'];
        }else{
            return 0;
        }
    }
}
