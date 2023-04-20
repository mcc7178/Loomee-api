<?php


namespace App\Services;


use App\Model\ActivateConfInvite;
use App\Model\Activity;
use App\Model\ConfInviteBonus;
use App\Model\FinanceLog;
use App\Model\MineConf;
use App\Model\MineUser;
use App\Model\MineUserPower;
use App\Model\MineUserPowerLog;
use App\Model\OnChain;
use App\Model\User;
use App\Model\UserAssetRecharges;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ActivationUserService
{
    protected static $coin = 'tmc';

    /**
     * @param $user_id        int 当前用户
     * @param $activationCode string 上级码
     * @return bool
     * @throws \Exception
     */
    public static function activationUser($user_id, $activationCode)
    {
        // 确认是否已激活
        $user = User::query()->find($user_id);

        if ($user->activation_status == 1) {
            throw new \Exception("已激活");
        }

        $parentData = $user = User::query()->where(['activation_code' => $activationCode, 'activation_status' => 1])->first();

        if (!$user)
            throw new \Exception(trans('msg.Trust code error'));

        $conf = self::getConf();
        if (!$conf) {
            throw new \Exception("暂未开放");
        }


        // TODO 确认扣除的资产数量
        $assetCondition = ['userid' => $user_id, 'coin' => $conf->consume_symbol];
        $asset = UserAssetRecharges::query()
            ->firstOrCreate($assetCondition);


        if ($asset->quantity < $conf->activation_quantity) {
            throw new \Exception(strtoupper($conf->consume_symbol) . trans('msg.insufficient balance'));
        }

//         激活扣除
        if ($conf->activation_quantity > 0) {
//            MineUserAssetLogic::createdAssetLog($user_id, $conf->activation_quantity, 'activation_consume', 0, $conf->consume_symbol);
            $finance_data = [
                'coin' => $conf->consume_symbol,
                'behavior' => 'activation_consume',
                'behavior_id' => 0,
                'remark' => '激活扣除',
                'account' => 'asset',
                'status' => 1,
                'freeze' => 0,
                'quantity' => -$conf->activation_quantity,
                'userid' => $user_id,
                'old_quantity' => $asset->quantity ?? 0,
                'old_freeze' => $asset->freeze ?? 0,
                'new_quantity' => bcsub($asset->quantity, $conf->activation_quantity),
                'new_freeze' => $asset->freeze ?? 0,
                'created_at' => time(),
            ];
//            FinanceService::addLog($user_id, $finance_data);

            FinanceLog::query()->forceCreate($finance_data);
            UserAssetRecharges::query()->where($assetCondition)->decrement('quantity', $conf->activation_quantity);
        }

        $belongData = self::getChildLevel($parentData->id, $conf->matrix_level);

        $updateData = [
            'from_uid' => $parentData->id,
            'node_id' => $belongData['node_id'],
            'node_position' => $belongData['node_position'],
            'belong_level' => $belongData['belong_level'],
            'activation_status' => 1,
            'activation_at' => time()
        ];

        User::query()->where('id', $user_id)->update($updateData);

        // 最高9层
        self::bonus($parentData->id, $conf, $user_id);
    }


    // 激活奖励
    public static function bonus($child_id, $conf, $user_id)
    {
        $bonus = [];
        $additionConf = ComputingPowerService::getAdditionConf();
        // 获取层级
        $parentData = BonusService::getParentData($child_id, 0);
        if ($conf->direct_push_bonus > 0) {

            UserAssetRecharges::query()->firstOrCreate([
                'userid' => $child_id,
                'coin' => $conf->bonus_symbol
            ]);

//            $finance_data = [
//                'coin' => $conf->bonus_symbol,
//                'behavior' => 'activation_invite_bonus',
//                'behavior_id' => 0,
//                'remark' => '激活奖励',
//                'account' => 'asset',
//                'status' => 1,
//                'freeze' => 0,
//                'quantity' => $conf->direct_push_bonus
//            ];
//
//            FinanceService::addLog($child_id, $finance_data);
//            UserAssetRecharges::query()->where([
//                'userid' => $child_id,
//                'coin' => $conf->bonus_symbol
//            ])->increment('quantity', $conf->direct_push_bonus);

            $param = [
                'userid' => $child_id,
                'coin' => $conf->bonus_symbol,
                'amount' => $conf->direct_push_bonus,
                'behavior' => 'activation_invite_bonus',
                'from_userid' => $user_id,
            ];
            CoinService::onChain($param);

//            MineUserAssetLogic::createdAssetLog($child_id, $conf->direct_push_bonus, 'activation_invite_bonus', 0, $conf->bonus_symbol, $user_id);
        }

        //拿层奖配置


        // 查看上级有效直推
        if ($parentData) {
            $config = ActivateConfInvite::query()->latest('invite_number')->pluck('level_number', 'invite_number');
            foreach ($parentData as $item) {
                $item->lvl = $item->lvl + 1;

                if ($item->lvl > $conf->dynamic_max_lvl)
                    continue;
//                var_dump('id:'.$item->id);
//                var_dump('层级:'.$item->lvl);
                $children = BonusService::getEffectiveChildUser($item->id);
//                var_dump('直推:'.$children['count']);
//                var_dump('拿几层:'.$config[$children['count']]);
                if (isset($config[$children['count']])) {
                    if ($config[$children['count']] < $item->lvl) {
                        continue;
                    }
                    $bonus[$item->id] = $conf->dynamic_bonus;
                } else {
                    foreach ($config as $inviteNumber => $level) {
                        if ($inviteNumber <= $children['count'] && $level < $item->lvl) {
                            continue;
                        }
                        $bonus[$item->id] = $conf->dynamic_bonus;
                    }
                }
//                var_dump($bonus);die;
            }
        }

        Log::info('$bonus.' . json_encode($bonus));
        $totalQuantity = 0;
        if (!empty($bonus)) {
            foreach ($bonus as $parentUserId => $quantity) {
                $totalQuantity += $quantity;
                $userAsset = UserAssetRecharges::query()->firstOrCreate([
                    'userid' => $parentUserId,
                    'coin' => $conf->bonus_symbol
                ]);
//                UserAssetRecharges::query()->where([
//                    'userid' => $parentUserId,
//                    'coin' => $conf->bonus_symbol
//                ])->increment('quantity', $quantity);
//
//                $insert_data = [
//                    'userid' => $parentUserId,
//                    'coin' => $conf->bonus_symbol,
//                    'old_quantity' => $userAsset->quantity ?? 0,
//                    'old_freeze' => $userAsset->freeze ?? 0,
//                    'new_quantity' => $userAsset->quantity ? ($userAsset->quantity + $quantity) : $quantity,
//                    'new_freeze' => $userAsset->freeze ?? 0,
//                    'quantity' => $quantity,
//                    'freeze' => 0,
//                    'behavior' => 'activation_dynamic',
//                    'behavior_id' => 0,
//                    'remark' => '激活层奖',
//                    'created_at' => time(),
//                    'account' => 'asset',
//                    'status' => 1,
//                ];
//                FinanceLog::query()->forceCreate($insert_data);

                $param = [
                    'userid' => $parentUserId,
                    'coin' => $conf->bonus_symbol,
                    'amount' => $quantity,
                    'behavior' => 'activation_dynamic',
                    'from_userid' => $user_id,
                ];
                CoinService::onChain($param);
            }
        }

        if (($destroy = $conf->activation_quantity - $conf->direct_push_bonus - $totalQuantity) > 0) {
            $param = [
                'userid' => 0,
                'coin' => $conf->bonus_symbol,
                'amount' => $destroy,
                'behavior' => 'activation_dynamic',
                'from_userid' => $user_id,
                'to' => CoinService::BLACK_HOLE_ADDRESS,
            ];
            CoinService::onChain($param);
        }


    }

    /**
     * 升级
     * @param $parent_id int 直推上级的ID
     * @param $nodeConf  object 配置
     */
    protected static function userUpgrade($parent_id, $nodeConf)
    {
        // 获取上级
//        $parentData = BonusService::getFromParentData($parent_id, 1);
        $parentData = BonusService::getFromParentData($parent_id);
        if ($parentData) {
            foreach ($parentData as $item) {
                $res = self::checkUserLevel($item->id, $item->node_level_id, $nodeConf);
                if ($item->node_level_id < $res['level_id']) {
                    User::query()->where('id', $item->id)->update([
                        'node_level_id' => $res['level_id']
                    ]);
                }
            }
        }
    }


    // belong_level 属于直推的第几层
    // node_position 放置位置_第几个
    /**
     * @param     $parent_id
     * @param int $belong_level
     * @param int $level_max
     * @return array
     */
    public static function getChildLevel($parent_id, $level_max = 9, $belong_level = 1)
    {
        // 获取当前这个用户的所有下级
        $userData = BonusService::getNodeUserChild($parent_id, 0);
        foreach ($userData as $k => $userDatum) {

            // 節點放置人數
            $childCount = self::getUserNodeCount($userDatum->id);
            // 節點满了
            if ($childCount < $level_max) {
                if ($k == 0)
                    $inviteLevel = 0;
                else
                    $inviteLevel = bcdiv($k, $level_max) + 1;
                return [
                    'node_id' => $userDatum->id,
                    'node_position' => bcadd($childCount, 1),
                    'belong_level' => $belong_level + $inviteLevel
                ];
            }
        }
        /*// 上级推荐的人数
        $childCount = User::query()->where([
            'node_id' => $parent_id
        ])->count();
        // 当上级放置位置满了的时候
        if ($childCount >= $level_max) {
            // 最左边的位置的人
            $firstChild = User::query()->where([
                'node_id' => $parent_id
            ])->orderBy('node_position', 'asc')->first();
            // 直推位置+ 1
            $belong_level = $belong_level + 1;
            return self::getChildLevel($firstChild->id, $user_id, $belong_level);
        } else {
            return [
                'node_id' => $parent_id,
                'node_position' => bcadd($childCount, 1),
                'belong_level' => $belong_level
            ];
        }*/
    }

    public static function getUserNodeCount($user_id)
    {
        return User::query()->where('node_id', $user_id)->count() ?: 0;
    }

    /**
     *
     * @param $user_id  int 用户id
     * @param $quantity float 算力数量
     */
    public static function additionBonus($user_id, $quantity, $mine_id)
    {

        $x = $user_id;
        ComputingPowerService::updateUserPower([
            'user_id' => $user_id,
            'type' => 'mine',
            'quantity' => $quantity,
            'created_at' => time(),
            'mine_id' => $mine_id,
            'direct_push_number' => 0,
            'invite_number' => 0,
            'node_id' => 0,
            'child_id' => 0
        ]);


        $inviteConf = ComputingPowerService::getInviteConf();
        $nodeConf = ComputingPowerService::getNodeConf();
        $nodeBonusData = [];

        // 推荐关系 所有的上级 包含自己
        $parentData = BonusService::getFromParentData($user_id, 0);


        foreach ($parentData as $item) {

            $is_invite_child = 0;
            if ($item->lvl == 1)
                $is_invite_child = 1;

            // 直推的下级
            $child = BonusService::getEffectiveChildUser($item->id);

            if ($item->lvl !== 0) {
                // 推广奖励
                self::inviteBonus($inviteConf, $item, $child, $quantity, $mine_id, $is_invite_child, $user_id);

                // 节点
                if ($item->node_level_id > 0) {
                    $nodeBonusData[$item->id] = $item->node_level_id;
                }
            }
        }


        if (!empty($nodeBonusData)) {

            // 当上级比下级小的时候，平级，再平级的时候 没有
            $logKey = 0;
            $bonusUserLogs = [];
            $log = [];

            foreach ($nodeBonusData as $user_id => $level) {
                $res = MineUser::query()->where(['user_id' => $user_id])->count();
                if (!$res) {
//                    echo $user_id;die;
                    continue;
                }

                $bonus_ratio = $nodeConf[$level]->bonus_ratio;
                $same_level_ratio = $nodeConf[$level]->same_level_ratio;
                if ($logKey == 0) {
                    $userBonusLogs[] = [
                        'user_id' => $user_id,
                        'quantity' => bcmul(bcdiv($bonus_ratio, 100, 4), $quantity, 4),
                        'lvl' => $level,
                        'type' => '正常1'
                    ];
                    $log[] = [
                        'level' => $level,
                        'is_ping' => 0
                    ];
                    $logKey++;
                } else {
                    $inLog = false;

                    foreach ($log as $k => &$l) {
                        // 自己比别人小 或者有人拿过
                        if ($level <= $l['level']) {
                            // 有人拿了
                            if ($l['is_ping'] == 1) {
                                $userBonusLogs[] = [
                                    'user_id' => $user_id,
                                    'quantity' => 0,
                                    'lvl' => $level,
                                    'type' => '没了'
                                ];
                            } else {
                                // 没人拿
                                $userBonusLogs[] = [
                                    'user_id' => $user_id,
                                    'quantity' => bcmul(bcdiv($same_level_ratio, 100, 4), $quantity, 4),
                                    'lvl' => $level,
                                    'type' => '平级'
                                ];
                                $l['is_ping'] = 1;
                            }
                            $inLog = true;
                            break;
                        }

                    }


                    if (!$inLog) {
                        // 正常拿
                        $userBonusLogs[] = [
                            'user_id' => $user_id,
                            'quantity' => bcmul(bcdiv($bonus_ratio, 100, 4), $quantity, 4),
                            'lvl' => $level,
                            'type' => '正常2'

                        ];
                        $log[] = [
                            'level' => $level,
                            'is_ping' => 0
                        ];
                    }

                }
            }

//            Log::info('$userBonusLogs: '. json_encode($userBonusLogs));
//            Log::info('$log: '. json_encode($log));die;

            if ($userBonusLogs) {
                foreach ($userBonusLogs as $bonusUserLog) {
                    if ($bonusUserLog['quantity'] < 0.0001)
                        continue;
                    $quantityPower = self::getUserPowerMaxDiff($bonusUserLog['user_id'], $bonusUserLog['quantity']);
                    if ($quantityPower <= 0)
                        continue;
                    ComputingPowerService::updateUserPower([
                        'user_id' => $bonusUserLog['user_id'],
                        'type' => 'node',
                        'quantity' => $quantityPower,
                        'created_at' => time(),
                        'mine_id' => $mine_id,
                        'direct_push_number' => 0,
                        'invite_number' => 0,
                        'node_id' => $bonusUserLog['lvl'],
                        'child_id' => 0
                    ]);
                }
            }
        }

        $nodeConf = ComputingPowerService::getNodeConf();

        self::userUpgrade($x, $nodeConf);

    }


    // 推广--- 自身、直推必须有矿机
    public static function inviteBonus($inviteConf, $item, $child, $quantity, $mine_id, $is_invite_child, $user_id)
    {
        $haveMineCount = $inviteNumber = 0;
        $mineUser = MineUser::query()->where(['user_id' => $item->id])->count();
        if ($mineUser > 0) {
            // 获取拥有矿机的下级
            $ids = [];
            foreach ($child['data'] as $datum) {
                $res = MineUser::query()->where(['user_id' => $datum->id])->count();
                if ($res) {
                    $haveMineCount++;
                    $ids[] = $datum->id;
                }
            }

            if ($item->lvl == 1 && $is_invite_child == 1 && !in_array($user_id, $ids))
                $haveMineCount++;

            $inviteRatioData = ComputingPowerService::getUserInvite($inviteConf, $item->lvl, $haveMineCount, $item->id);


            Log::info($item->id . ': $inviteRatioData: ' . json_encode($inviteRatioData));

            if ($inviteRatioData['ratio'] > 0) {
                //  invite_number 对应 推广奖励 直推人数
                $bonusQuantity = bcmul($quantity, $inviteRatioData['ratio'] / 100, 4);

                Log::info('inviteBonus:进入算力：' . $bonusQuantity);

                $quantityPower = self::getUserPowerMaxDiff($item->id, $bonusQuantity);

                Log::info('inviteBonus:返回算力：' . $quantityPower);


                if ($quantityPower > 0) {
                    ComputingPowerService::updateUserPower([
                        'user_id' => $item->id,
                        'type' => 'invite',
                        'quantity' => $quantityPower,
                        'created_at' => time(),
                        'mine_id' => $mine_id,
                        'direct_push_number' => 0,
                        'invite_number' => $inviteRatioData['invite_number'],
                        'node_id' => 0,
                        'child_id' => $user_id
                    ]);
                }

            }

        }
    }

    public static function getConf()
    {
        return ConfInviteBonus::query()->first();
    }

    /**
     * @param $user_id
     * @param $user_node_id
     * @param $conf
     * @return int[]
     */
    public static function checkUserLevel($user_id, $user_node_id, $conf)
    {
        // 获取团队业绩
        $userTeamPerformance = BonusService::getUserMinMaxAmount($user_id);

        $bonus_ratio = 0;
        $same_level_ratio = 0;
        $level_id = 0;

        if ($user_node_id > 0) {
            $bonus_ratio = $conf[$user_node_id]->bonus_ratio;
            $same_level_ratio = $conf[$user_node_id]->same_level_ratio;
            $level_id = $user_node_id;
        }

        $amount = bcadd($userTeamPerformance['min'], $userTeamPerformance['max'], 4);
        $amount = bcadd($amount, $userTeamPerformance['my'], 4);


        foreach ($conf as $item) {
            if ($item->id < $user_node_id)
                continue;

            if ($item->id <= $level_id)
                continue;


            if ($item->line) {
                // 存在需要线级别
                // 获取直推的用户的级别 并列出数量
                $childUserData = User::query()->where(['from_uid' => $user_id])->get();
                $lineList = 0;
                foreach ($childUserData as $i) {
                    if ($i->node_level_id >= $item->line_level) {
                        $lineList++;
                    }
                }

                if ($lineList >= $item->line && $amount >= $item->team_performance) {
                    $bonus_ratio = $item->bonus_ratio;
                    $same_level_ratio = $item->same_level_ratio;
                    $level_id = $item->id;
                }

            } else {

                if ($amount >= $item->team_performance) {
                    $bonus_ratio = $item->bonus_ratio;
                    $same_level_ratio = $item->same_level_ratio;
                    $level_id = $item->id;
                }
            }


        }
        return [
            'bonus_ratio' => $bonus_ratio, // 伞下算力百分比
            'same_level_ratio' => $same_level_ratio, // 平级
            'level_id' => $level_id
        ];
    }

    public static function getUserPowerMaxDiff($user_id, $quantity)
    {
        $computing_power = MineUserPower::query()->where('user_id', $user_id)
            ->whereIn('type', ['candy', 'invite', 'node'])->sum('quantity') ?: 0;

        Log::info('已存在算力：' . $user_id . '： ' . $computing_power);

        $mineConf = MineConf::query()->get()->keyBy('id');

        $userMine = MineUser::query()->where('user_id', $user_id)->get();
        $userMinePower = 0;
        if ($userMine) {
            foreach ($userMine as $item) {
                $userMinePower += bcmul($item->power, $mineConf[$item->mine_id]->max_power_times, 4);
            }
        } else {
            return 0;
        }

        Log::info('矿机算力：' . $user_id . '： ' . $userMinePower);


        // 应有的总算力 减去 已有的算力 得到还能得到的算力
        Log::info('减法：' . $userMinePower . '： ' . $computing_power);

        $diffPower = bcsub($userMinePower, $computing_power, 4);
        if ($diffPower <= 0)
            return 0;

        Log::info('得到还能得到的算力：差：' . $diffPower . '： 原：' . $quantity);

        if ($diffPower > $quantity) {
            return $quantity;
        }


        return $diffPower;
    }

}


