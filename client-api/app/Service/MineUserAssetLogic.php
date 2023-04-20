<?php
namespace App\Services;

use App\Model\MineUserAssetLog;

class MineUserAssetLogic
{
    public static function createdAssetLog($user_id, $direct_push_bonus, $action, $mine_id = 0, $coin = 'mt', $child_id = 0)
    {
        MineUserAssetLog::query()->insertGetId([
            'user_id' => $user_id,
            'quantity' => $direct_push_bonus,
            'action' => $action,
            'created_at' => time(),
            'mine_id' =>$mine_id,
            'coin' => $coin,
            'child_id' => $child_id
        ]);
    }
}
