<?php


namespace App\Logic;


use App\Exceptions\BaseException;
use App\Lib\Redis;
use App\Model\Markets;
use App\Services\KlineService;

class MarketRedisLogic
{
    public static function getMarketKlineHistory($market, $time)
    {
         $market = strtolower($market);
        if (strpos($market, '/') !== false) {
            list($symbol, $block) = explode('/', $market);
            $HBmarket = $symbol . $block;
            $Flmarket = strtolower($market);

        }
        else
        {
            $form = explode('usdt', $market);
            $Flmarket =  $form[0].'/usdt' ;
            $HBmarket = $market;
        }

        $marketReptileStatus = Markets::query()
            ->where('name', strtolower($Flmarket))
            ->first();
        if ($marketReptileStatus->reptile_status == 1) {
            $key = 'market_' . $HBmarket;

            $redis = Redis::getInstance();
            if (!$redis->hExists($key, $time)) {
                return [];
            }

            $data = $redis->hGet($key, $time);
            $data = json_decode($data, true);

        } else {
            $data = KlineService::getKline($market, $time);
        }
        return $data;

        $return = [];

        foreach ($data as $key => $kline) {
            if (!$kline && !empty($return)) {
                $item = end($return);
                if (!$item) {
                    continue;
                }
                if (strpos($type, 'min') !== false) {
                    $cardinal = str_replace('min', '', $type);
                    $time = $item['time'] + ($cardinal * 60);
                } else {
                    $time = $item['time'] + 60 * 60 * 24;
                }
                $new_item['time'] = $time;
                $new_item['high'] = $item['close'];
                $new_item['low'] = $item['close'];
                $new_item['open'] = $item['close'];
                $new_item['close'] = $item['close'];
                $new_item['volume'] = 0;
                $new_item['amount'] = 0;
                $return[] = $new_item;
                $type = KlineService::getKlineTypeFormat($type);
                if (!$type) {
                    continue;
                }
                KlineService::addOneKline($market, $type, $time, $new_item);
                continue;
            }
            $item['amount'] = (float)number_format($kline['amount'], $market_data->decimals_number, '.', '');
            $item['volume'] = (float)number_format($kline['amount'], $market_data->decimals_number, '.', '');
            $item['high'] = (float)number_format($kline['high'], $market_data->decimals_price, '.', '');
            $item['low'] = (float)number_format($kline['low'], $market_data->decimals_price, '.', '');
            $item['open'] = (float)number_format($kline['open'], $market_data->decimals_price, '.', '');
            $item['close'] = (float)number_format($kline['close'], $market_data->decimals_price, '.', '');
            $item['time'] = $kline['time'];
            $return[] = $item;
        }

        return $return;


    }
}
