<?php

namespace App\Services;

use App\Model\UserAssetMinings;
use App\Model\UserBonusDynamicLog;
use App\Model\UserBonusLog;
use App\Model\Withdraw;
use App\Services\BaseService;
use App\Model\User;
use App\Model\UserLoginLog;
use App\Lib\Redis;
use App\Model\UserAssetRecharges;
use Illuminate\Support\Facades\DB;
use App\Model\UserAssetDeals;

class WithdrawService extends BaseService
{

    public static function getTradeUsdtQuantity($userId)
    {
        $coin = env('PLATFORM_COIN');
        $TableName = "finish_trade_{$coin}_usdt_log";
        return DB::table($TableName)->where('buy_user_id', $userId)->sum('amount');
    }

    public static function getWithdrawUsdtQuantity($userId)
    {
        return Withdraw::query()->where(['userid' => $userId, 'coin' => 'usdt'])->sum('number');
    }

    public static function getWithdrawBalance($userId)
    {
        return self::getTradeUsdtQuantity($userId) - self::getWithdrawUsdtQuantity($userId);
    }

}
