<?php


namespace App\Logic;


use App\Lib\Redis;
use Carbon\Carbon;

class WithdrawLogic
{
    /**
     * 插入审核队列
     * @param int $withdrawId
     * @param int $count
     * @param array $extend
     */
    public static function pushCheckWithdraw(int $withdrawId)
    {
        Redis::getInstance()->rPush('check_withdraw', json_encode([
            'withdraw_id' => $withdrawId,
            'created_at' => Carbon::now()->toDateTimeString()
        ]));
    }
}
