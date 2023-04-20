<?php


namespace App\Services;


use App\Lib\Redis;
use App\Model\CtcConfSymbol;

class CtcSymbolService extends BaseService
{
    // c2c币种设置
    public static function symbolConf($symbol)
    {
        $symbol = strtotime($symbol);
        if (Redis::getInstance(4)->hExists('ctc_symbol_conf', $symbol)){
            $conf = Redis::getInstance(4)->hGet('ctc_symbol_conf', $symbol);
            return json_decode($conf);
        }
        $conf = CtcConfSymbol::query()->where('symbol', $symbol)->first();
        Redis::getInstance(4)->hSet('ctc_symbol_conf', $symbol, json_encode($conf));
        return $conf;
    }
}