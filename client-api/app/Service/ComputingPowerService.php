<?php


namespace App\Services;


use App\Model\MineConfActivation;
use App\Model\MineConfAddition;
use App\Model\MineConfInvite;
use App\Model\MineConfNode;
use App\Model\MineUserPower;
use App\Model\MineUserPowerLog;
use App\Model\User;
use Illuminate\Support\Facades\Log;

class ComputingPowerService
{

    // 推广算力加成
    public static function addition($user_id, $conf)
    {
        if (!$conf)
            return 0;

        //

    }


    /**
     * @param $conf
     * @param $inviteNumber int 直推数量
     * @param $teamNumber   int 团队人数
     * @param $inviteNumberLog int 上一次的人数
     * @return array|int[]
     */
    public static function getUserAddition($conf, $inviteNumber, $teamNumber, $inviteNumberLog)
    {
        // 判断是否已领取
        foreach ($conf as $item) {
            if ($item->direct_push_number <= $inviteNumber && $item->team_number <= $teamNumber &&  $item->direct_push_number > $inviteNumberLog )
            {
                return ['quantity' => $item->computing_power_bonus, 'invite_number' => $item->invite_number];
            }
        }

        return ['quantity' => 0];
    }

    public static function getUserInvite($conf, $lvl, $inviteNumber, $uid)
    {
        $invite_number = 0;
        $level_bonus_ratio = 0;

            foreach ($conf as $item) {
                if ($item->invite_number <= $inviteNumber && $lvl == $item->level_number)
                {
                    $invite_number = $item->invite_number;
                    $level_bonus_ratio = $item->level_bonus_ratio;
                    break;
                }
        }

        return ['ratio' => $level_bonus_ratio, 'invite_number' => $invite_number];
    }

    public static function getUserNode($conf, $node_id)
    {
        if (isset($conf[$node_id]))
        {
            return [
                'bonus_ratio' => $conf[$node_id]->bonus_ratio,
                'same_level_ratio' => $conf[$node_id]->same_level_retio,
            ];
        }
        return [];


    }

    public static function getActivationConf()
    {
        return MineConfActivation::query()->first();
    }

    public static function getAdditionConf()
    {
        return MineConfAddition::query()->orderByDesc('direct_push_number')->get()->keyBy('id');
    }


    public static function getInviteConf()
    {
        return MineConfInvite::query()->orderBy('invite_number', 'asc')->get()->keyBy('id');
    }

    public static function getNodeConf()
    {
        return MineConfNode::query()->get()->keyBy('id');
    }

    public static function updateUserPower($data)
    {
        $data['action'] = $data['type'];
        $res = MineUserPower::query()->where([
            'user_id' => $data['user_id'],
            'type' => $data['type']
        ])->first();


        User::query()->where('id', $data['user_id'])->increment('computing_power', $data['quantity']);

        // direct_push_number	对应 推广算力加成 直推人数
        unset($data['type']);
        MineUserPowerLog::query()
            ->insertGetId($data);

        if($res)
        {
            // update
            $res->increment('quantity', $data['quantity']);
            $res->updated_at = time();
            return $res->save();
        }

        return MineUserPower::query()->insertGetId([
            'user_id' => $data['user_id'],
            'type' => $data['action'],
            'quantity' => $data['quantity'],
            'updated_at' => time()
        ]);

    }

}
