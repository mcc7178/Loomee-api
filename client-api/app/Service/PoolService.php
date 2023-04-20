<?php

namespace App\Services;

use App\Lib\Redis;
use App\Model\FinanceLog;
use App\Model\MinePool;
use App\Model\MinePoolConf;
use App\Model\MinePoolLog;
use App\Model\MinePoolMinerConf;
use App\Model\PowerConfGravitation;
use App\Model\PowerConfInvite;
use App\Model\PowerConfReturn;
use App\Model\PowerConfSlide;
use App\Model\PowerReturnKey;
use App\Model\ReturnStatusChange;
use App\Model\User;
use App\Model\UserAssetRecharges;
use App\Model\UserPower;
use App\Model\UserPowerLog;
use App\Model\UserPowerRelation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use mysql_xdevapi\Exception;


class PoolService extends BaseService
{

    const MOTHER_COIN = 'lac';
    const CHILDREN_COIN = 'lbc';
    const COST_COIN = 'usdt';
    const GET_KEY_SECOND = self::GET_KEY_DAY * 86400;
    const GET_KEY_DAY = 30;

    public static function intoList($params)
    {
        $redis = Redis::getInstance();

        $res = $redis->lPush('return:list', serialize($params));
    }

    public static function inList($params)
    {
        $redis = Redis::getInstance();
        $res = $redis->lPush('power:list', serialize($params));
    }

    public static function outList($listData)
    {
        $redis = Redis::getInstance();
        try {

            $powerConfReturn = PowerConfReturn::query()->get()->keyBy('level');//        $res = $redis->lIndex('power:list', 0);//测试用
            list('user_id' => $userId, 'quantity' => $quantity, 'created_at' => $createdAt, 'id' => $logId) = unserialize($listData[1]);
            var_dump($userId);
            var_dump($quantity);
            var_dump($createdAt);
            $time = time();
            var_dump($logId);//        sleep(10000);
            if (!$userId) {
                return false;
            }
            bcscale(8);
            $powerArr = [];
            DB::beginTransaction();
            //算力
            $minePool = MinePool::query()->where('user_id', $userId)->first();
            var_dump('我的ID：' . $userId);
            $minePoolConf = MinePoolConf::query()->first();
            $burnEntropyTimes = $minePoolConf->burn_entropy_times;//烧墒触发倍数  改為以最大的倍數為基礎算力
            //引力奖算力
            $config = PowerConfGravitation::query()->oldest('get_bonus_level')->get();
            $levelUsers = BonusService::getParentData($userId);
            if ($userId == 878075) {
                //                        var_dump($config);
                //                        var_dump($levelUsers);die;
            }
            foreach ($levelUsers as $parent) {
                $profitConfig = [];
                foreach ($config as $configItem) {
                    if ($parent->lvl > $configItem->get_bonus_level) {
                        $profitConfig = $configItem;
                        continue;
                    } else if ($parent->lvl == $configItem->get_bonus_level) {
                        $profitConfig = $configItem;
                        break;
                    } else {
                        continue 2;
                    }
                }
                //            if ($userId == 878075 && $parent->id == 878028) {
                //                            var_dump($parent->id . '---', $profitConfig);die;
                //            }
                if ($profitConfig) {
                    $parentMinePool = MinePool::query()->where('user_id', $parent->id)->first();
                    $hold = $parentMinePool['active'] ?? 0;
                    if ($hold < $profitConfig->hold) {
                        var_dump("$userId 上級 " . $parent->id . "引力 $profitConfig->get_bonus_level 層" . ' 个人有效持币不达标');
                        continue;
                    }//个人有效持币
                    //                                var_dump(User::query()->where('from_uid', $parent->id)->count());
                    if (User::query()->where('from_uid', $parent->id)->count() < $profitConfig->direct_recommend) {
                        var_dump("$userId 上級 " . $parent->id . "引力 $profitConfig->get_bonus_level 層 直推人數不達標");
                        continue;
                    }//直推个数

                    $direct = User::query()->where('from_uid', $parent->id)->select('id')->get()->toArray();
                    //                                var_dump($direct);
                    $num = MinePool::query()->whereIn('user_id', $direct)->where('active', '>=', $profitConfig->team_per_hold)->count();
                    //                                var_dump($num);
                    if ($num < $profitConfig->direct_recommend) {
                        var_dump("$userId 上級 " . $parent->id . "引力 $profitConfig->get_bonus_level 層" . "直推每人持币量 $profitConfig->team_per_hold 不达标");

                        continue;
                    }//直推人有效持币
                    //引力奖达标
                    $gravitationPower = 0;
                    //                                var_dump($minePool->power);
                    //                                var_dump($minePool->id);
                    //烧墒
                    $basePower = PoolService::calculateBurn($minePool->power, $parentMinePool->power, $burnEntropyTimes);
                    $powerArr[$parent->id]['gravitation'] = bcadd($powerArr[$parent->id]['gravitation'] ?? 0, bcmul($basePower, $profitConfig->reward_power_ratio));
                    //测试用
                    $powerArr[$parent->id]['gravitation_detail'][$userId . ' ' . $profitConfig->get_bonus_level . '層'] = bcmul($basePower, $profitConfig->reward_power_ratio);
                    //入庫
                    $powerArr[$parent->id]['gravitation_data'][$userId] = ['level' => $profitConfig->get_bonus_level, 'ratio' => $profitConfig->reward_power_ratio, 'quantity' => bcmul($basePower, $profitConfig->reward_power_ratio)];
                    $powerArr[$parent->id]['insert_data']['gravitation'][$userId] = ['level' => $profitConfig->get_bonus_level, 'ratio' => $profitConfig->reward_power_ratio, 'quantity' => bcmul($basePower, $profitConfig->reward_power_ratio)];
                }

            }

            // 推荐奖励/加速宝
            $parentUsers = BonusService::getFromParentData($userId);
            var_dump($parentUsers);
            if ($userId == 878061) {
                var_dump('直推奖，我的上级：', $parentUsers);
            }
            $config = PowerConfInvite::query()->oldest('get_bonus_level')->get();
            foreach ($parentUsers as $parent) {
                $profitConfig = [];
                //                        if ($userId == 878063 && $parent->id == 878028) {
                foreach ($config as $configItem) {
                    var_dump($configItem->get_bonus_level);
                    if ($parent->lvl > $configItem->get_bonus_level) {
                        $profitConfig = $configItem;
                        continue;
                    } else if ($parent->lvl == $configItem->get_bonus_level) {
                        $profitConfig = $configItem;
                        break;
                    } else {
                        var_dump("$userId 的上级 $parent->id" . '不达标' . $parent->lvl . '配置等级' . $configItem->get_bonus_level);
                        continue 2;
                    }
                    //                        }
                    //                            var_dump('最终111配置：', $profitConfig);
                }
                //                        var_dump('最终配置：',$profitConfig);die;
                if ($userId == 878064 && $parent->id == 878028) {
                    var_dump('最终配置：', $profitConfig);
                }
                //                        var_dump($parent->id .'---',$profitConfig);
                if ($profitConfig) {
                    $parentMinePool = MinePool::query()->where('user_id', $parent->id)->first();
                    $hold = $parentMinePool['active'] ?? 0;
                    //                                var_dump($hold);
                    if ($hold < $profitConfig->hold) {
                        var_dump($parent->id . ' 个人有效持币不达标');
                        continue;
                    }//个人有效持币
                    if (!$parentMinePool->power) {
                        var_dump($parent->id . ' 个人算力不正常');
                        die;
                        continue;
                    }//个人有效持币
                    var_dump($parent->id . '直推人数：' . User::query()->where('from_uid', $parent->id)->count());
                    if (User::query()->where('from_uid', $parent->id)->count() < $profitConfig->direct_recommend) {
                        var_dump($parent->id . '直推个数不达标');
                        continue;
                    }//直推个数

                    $direct = User::query()->where('from_uid', $parent->id)->select('id')->get()->toArray();
                    //                                var_dump($direct);
                    $num = MinePool::query()->whereIn('user_id', $direct)->where('active', '>=', $profitConfig->team_per_hold)->count();
                    //                                var_dump($num);
                    if ($num < $profitConfig->direct_recommend) {
                        var_dump($parent->id . "直推人均持币量 $profitConfig->team_per_hold 不达标");
                        continue;
                    }//直推人有效持币
                    //                                var_dump($minePool->power);
                    //                                var_dump($minePool->id);

                    $basePower = PoolService::calculateBurn($minePool->power, $parentMinePool->power, $burnEntropyTimes);
                    $powerArr[$parent->id]['invite'] = bcadd($powerArr[$parent->id]['invite'] ?? 0, bcmul($basePower, $profitConfig->reward_power_ratio));
                    //直推细节 测试用
                    var_dump($userId . '给' . $parent->id . '直推算力：' . bcmul($basePower, $profitConfig->reward_power_ratio));
                    $powerArr[$parent->id]['invite_detail'][$userId] = bcmul($basePower, $profitConfig->reward_power_ratio);
                    $powerArr[$parent->id]['insert_data']['invite'][$userId] = ['level' => $profitConfig->get_bonus_level, 'ratio' => $profitConfig->reward_power_ratio, 'quantity' => bcmul($basePower, $profitConfig->reward_power_ratio)];
                }
            }//                        var_dump('推荐 上级',$parentUsers);die;

            // 滑落奖励  找直推，判断层级关系
            $directUserId = $parentUsers[0]->from_uid;//                        var_dump($levelUsers);
            $newLevelUsers = array_column($levelUsers, null, 'id');
            $slideLevel = $newLevelUsers[$directUserId]->lvl;// 一層也算滑落
            $slideConfigs = PowerConfSlide::query()->latest('get_bonus_level')->get()->keyBy('get_bonus_level');
            if ($slideLevel <= $minePoolConf->max_get_bonus_level) {
                foreach ($slideConfigs as $levels => $slideConf) {
                    if ($slideLevel >= $levels) {
                        $parentMinePool = MinePool::query()->whereUserId($directUserId)->first();
                        if (!$parentMinePool || $parentMinePool->power <= 0) {
                            break;
                        }
                        $basePower = PoolService::calculateBurn($minePool->power, $parentMinePool->power, $burnEntropyTimes);
                        var_dump('***滑落奖 start ***');
                        var_dump('我的id' . $userId);
                        var_dump('上级ID' . $directUserId);
                        var_dump('我的算力。' . $minePool->power);
                        var_dump('上级算力。' . $parentMinePool->power);
                        var_dump('取谁的算力。' . $basePower);
                        var_dump('滑落等级。' . $levels);
                        var_dump('滑落奖励比例。' . $slideConf->reward_power_ratio);
                        var_dump('***滑落奖 end ***');
                        /*if ($directUserId == '878028') {
                            var_dump('直推上级ID：'. $directUserId.'滑落ID'. $userId);
                        }*/
                        $powerArr[$directUserId]['slide'] = bcadd($powerArr[$directUserId]['slide'] ?? 0, bcmul($basePower, $slideConf->reward_power_ratio));
                        $powerArr[$directUserId]['insert_data']['slide'][$userId] = ['level' => $slideConf->get_bonus_level, 'ratio' => $slideConf->reward_power_ratio, 'quantity' => bcmul($basePower, $slideConf->reward_power_ratio)];
                        var_dump('滑落奖励：', $powerArr[$directUserId]['slide']);
                        break;
                    }
                }

            }//总奖励算力 保留三位小数，四舍五入

            //竞赛奖 应该都是减的
            foreach ($levelUsers as $parent) {
                $parentMinMax = BonusService::getUserMinMaxAmount($parent->node_id);
//                var_dump('userid  '.$parent->node_id.'',$parentMinMax);
                $res = $redis->SISMEMBER('return:set', $parent->node_id);
                //判断是否有达标记录,是否变为未达标
                $ReturnStatusChange = ReturnStatusChange::query()->where(['user_id' => $parent->node_id, 'type' => 'standard', 'is_effect' => 1])->first();
                if ($ReturnStatusChange) {
                    $returnConf = $powerConfReturn[$ReturnStatusChange->level];
                    $MinePool = MinePool::query()->whereUserId($parent->node_id)->first();
                    if (!self::calculateUserCompete($parentMinMax, $returnConf, $MinePool)) {
                        $currentTimeRatio = ($MinePool->hold_time_day * $minePoolConf->hold_daily_reward_ratio);
                        $changeLog = new ReturnStatusChange(['user_id' => $parent->node_id, 'from_user_id' => $userId, 'type' => 'demote', 'level' => $ReturnStatusChange->level, 'big_quantity' => $parentMinMax['max'], 'small_quantity' => $parentMinMax['min'], 'hold' => $MinePool->active, 'hold_power_ratio' => $currentTimeRatio, 'big_quantity_threshold' => $returnConf->big_quantity, 'small_quantity_threshold' => $returnConf->small_quantity, 'hold_threshold' => $returnConf->hold, 'hold_power_ratio_threshold' => $returnConf->hold_power_ratio, 'mine_pool_log_id' => $logId]);
                        $changeLog->save();
                        $ReturnStatusChange->is_effect = 0;
                        $ReturnStatusChange->save();
                        $redis->del('return:user:', $parent->node_id);
                        $redis->sRem('return:set', $parent->node_id);
                        //判断低等级是否达标 (不用考虑 只能从 v1 开始往上)
//                        for ($forLevel = $ReturnStatusChange->level - 1; $forLevel >= 0; $forLevel--) {
//                            self::calculateUserCompete($parentMinMax, $powerConfReturn[$forLevel], $MinePool);
//                        }
                    }
                }


                /*foreach ($powerConfReturn as $returnConf) {
                    $remove = 1;
                    $levelKey = 'lv' . $returnConf->level;
//                        var_dump($levelKey);
                    $ReturnStatusChange = ReturnStatusChange::query()->where(['user_id' => $parent->node_id, 'level' => $returnConf->level, 'type' => 'standard', 'is_effect' => 1])->first();
                    if (!$ReturnStatusChange) {
//                        if (empty($userReturnInfo[$levelKey])) {
                        continue;
                    }
                    //判断是否低于达标值
//                        var_dump($parent->node_id.'的小区:' . $parentMinMax['min']);
//                        var_dump('大区配置'.$returnConf->big_quantity);
//                        var_dump($parent->node_id.'的大区:' . $parentMinMax['max']);
//                        var_dump('小区配置' .$returnConf->small_quantity);

                    if ($parentMinMax['min'] >= $returnConf->small_quantity && $parentMinMax['max'] >= $returnConf->big_quantity) {
                        //判断有效持币 和 持币算力
                        $MinePool = MinePool::query()->whereUserId($userId)->first();
                        if ($MinePool->active >= $returnConf->hold && ($MinePool->hold_time_day * $minePoolConf->hold_daily_reward_ratio) >= $returnConf->hold_power_ratio) {
                            $remove = 0;
                        }
                    }
                    if ($remove) {
                        $MinePool = MinePool::query()->whereUserId($parent->node_id)->first();
                        $currentTimeRatio = ($MinePool->hold_time_day * $minePoolConf->hold_daily_reward_ratio);
                        $changeLog = new ReturnStatusChange(['user_id' => $parent->node_id, 'from_user_id' => $userId, 'type' => 'demote', 'level' => $returnConf->level, 'big_quantity' => $parentMinMax['max'], 'small_quantity' => $parentMinMax['min'], 'hold' => $MinePool->active, 'hold_power_ratio' => $currentTimeRatio, 'big_quantity_threshold' => $returnConf->big_quantity, 'small_quantity_threshold' => $returnConf->small_quantity, 'hold_threshold' => $returnConf->hold, 'hold_power_ratio_threshold' => $returnConf->hold_power_ratio, 'mine_pool_log_id' => $logId]);
                        $changeLog->save();
                        $ReturnStatusChange->is_effect = 0;
                        $ReturnStatusChange->save();
                        $redis->hMSet('return:user:' . $parent->node_id, [$levelKey => 0, $levelKey . '_time' => time()]);
//                        $redis->sRem('return:set', $parent->node_id);
//                        $redis->hDel('return:user:', $parent->node_id);
                    }
                }*/

            }

//            $minerConf = MinePoolMinerConf::query()->get()->keyBy('level');//结算
            $insertPowerLog = [];
            //旧算力关系处理
            UserPowerRelation::query()->where(['from_user_id' => $userId])->chunkById(1000, function ($query) use ($userId, $logId, $time, $createdAt, &$insertPowerLog, $powerArr) {
                foreach ($query as $item) {
                    $newPower = $powerArr[$item->user_id]['insert_data'][$item->type][$userId]['quantity'] ?? 0;
                    var_dump('$item->user_id' . $item->user_id);
                    var_dump('$userId' . $userId);
                    var_dump('powerArray', $powerArr ?? []);
                    var_dump('$powerArr[$item->user_id][insert_data][$item->type][$userId][quantity]', $powerArr[$item->user_id]['insert_data'][$item->type][$userId]['quantity'] ?? []);
                    var_dump('$newPower' . $newPower);
                    if ($newPower != $item->quantity) {
                        $changeQuantity = bcsub($newPower, $item->quantity, 8);
                        $insertPowerLog[] = ['from_user_id' => $userId, 'user_id' => $item->user_id, 'type' => $item->type, 'operated_at' => $createdAt, 'created_at' => $time, 'action' => 'out', 'mine_pool_log_id' => $logId, 'quantity' => $changeQuantity];
                        UserPower::query()->where('user_id', $item->user_id)->decrement($item->type, -$changeQuantity);
                        UserPowerRelation::query()->where(['user_id' => $item->user_id, 'from_user_id' => $userId, 'type' => $item->type])->update(['quantity' => $newPower]);
                    }
                }
//                throw new \Exception('xxx');

            });
            foreach ($powerArr as $userid => $powers) {
                if ($userid == 878119) {
                    var_dump($powers);
//                    die;
                }
                $currentMinePool = MinePool::query()->where('user_id', $userid)->where('status', 'normal')->first();

                if (!$currentMinePool) {
                    continue;
                }

                $dynamicPower = bcadd(bcadd($powers['invite'] ?? 0, $powers['gravitation'] ?? 0), $powers['slide'] ?? 0);
                var_dump($userid);
                var_dump($currentMinePool->level);
                //            $staticPower = bcadd($currentMinePool->power, $powers['time'], 8);
                //            $maxDynamicPower = empty($currentMinePool->level) ? 0 : bcmul($staticPower, $minerConf[$currentMinePool->level]['max_power_times']);
                //及算力上限
                var_dump('直推算力：' . ($powers['invite'] ?? 0));
                //                    if ($userid == 878028) {
                var_dump('直推细节：', $powers['invite_detail'] ?? []);
                //                    }
                var_dump('引力算力：' . ($powers['gravitation'] ?? 0));
                //                    if ($userid == 878061) {
                var_dump('引力细节：', $powers['gravitation_detail'] ?? []);
                //                    }
                var_dump('时长算力：' . ($powers['time'] ?? 0));
                var_dump('基础算力：' . ($currentMinePool->power ?? 0));
                var_dump('滑落算力：' . ($powers['slide'] ?? 0));
//                var_dump('动态算力：' . $dynamicPower);
                //            var_dump('静态算力：' . $staticPower);
                //算力
                //            UserPower::query()->updateOrCreate(['user_id' => $userid], ['invite' => $powers['invite'] ?? 0, 'slide' => $powers['slide'] ?? 0, 'gravitation' => $powers['gravitation'] ?? 0]);
                //            $dynamicPower = min($maxDynamicPower, $dynamicPower);
                //            $totalPower = bcadd($dynamicPower, $staticPower);
                //            var_dump('总算力：' . $totalPower);
                //            var_dump($powers['insert_data']);die;
                /*$powerLog = UserPowerRelation::query()->where(['from_user_id' => $userId, 'user_id' => $userid])->get();
                $insertPowerLog = [];
                if ($powerLog) {
                    foreach ($powerLog as $log) {
                        $currentPower = isset($powers['insert_data'][$log['type']][$userId]) ? $powers['insert_data'][$log['type']][$userId]['quantity'] : 0;
                        if ($currentPower != $log->quantity) {
                            $quantity = bcsub($currentPower, $log->quantity, 8);
                            $insertPowerLog[] = ['from_user_id' => $userId, 'user_id' => $userid, 'type' => $log['type'], 'operated_at' => $createdAt, 'created_at' => time(), 'action' => 'out', 'mine_pool_log_id' => $logId, 'quantity' => $quantity];
                            UserPower::query()->where('user_id', $userid)->decrement($log['type'], -$quantity);
                        }
                    }
                }*/

                //新算力处理
//                if (isset($powers['insert_data'])) {
//                    foreach ($powers['insert_data'] as $powerType => $powerLogs) {
//                        foreach ($powerLogs as $fromUserId => $powerLog) {
//                            if (!UserPowerRelation::query()->where(['user_id' => $userid, 'from_user_id' => $fromUserId, 'type' => $powerType])->first()){
//                                UserPowerRelation::query()->insert(['user_id' => $userid, 'from_user_id' => $fromUserId, 'type' => $powerType, 'quantity' => $powerLog['quantity'], 'ratio' => $powerLog['ratio'], 'level' => $powerLog['level']]);
//                                $insertPowerLog[] = ['from_user_id' => $userId, 'user_id' => $userid, 'type' => $powerType, 'operated_at' => $createdAt, 'created_at' => $time, 'action' => 'out', 'mine_pool_log_id' => $logId, 'quantity' => $quantity];
//
//                                UserPower::query()->where('user_id', $userid)->decrement($log['type'], -$quantity);
//                            }
//                        }
//                    }
//                }
            }
            UserPowerLog::query()->insert($insertPowerLog);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            $redis->lPush('power:list', $listData[1]);
            var_dump('list script incorrect: date' . new Carbon() . ' msg:' . $e->getLine() . $e->getMessage());
            die;
        }
    }


    public static function outReturnList()
    {
        $redis = Redis::getInstance();
        $powerConfReturn = PowerConfReturn::query()->get();

        $res = $redis->rPop('return:list');
        list($userId, $quantity) = unserialize($res);
        $parents = BonusService::getParentData($userId);
        $MinePoolConfig = MinePoolConf::query()->first();
        foreach ($parents as $parent) {
            $parentMinMax = BonusService::getUserMinMaxAmount($parent->node_id);
//                var_dump('userid  '.$parent->node_id.'',$parentMinMax);
            $res = $redis->SISMEMBER('return:set', $parent->node_id);
            if ($res) {//已有达标记录
                $userReturnInfo = $redis->hGetAll('return:user:' . $parent->node_id);
                $remove = 1;
                foreach ($powerConfReturn as $returnConf) {
                    $levelKey = 'lv' . $returnConf->level;
//                        var_dump($levelKey);
                    if (empty($userReturnInfo[$levelKey])) {
                        continue;
                    }
                    //判断是否低于达标值
//                        var_dump($parent->node_id.'的小区:' . $parentMinMax['min']);
//                        var_dump('大区配置'.$returnConf->big_quantity);
//                        var_dump($parent->node_id.'的大区:' . $parentMinMax['max']);
//                        var_dump('小区配置' .$returnConf->small_quantity);
                    if ($parentMinMax['min'] < $returnConf->small_quantity || $parentMinMax['max'] < $returnConf->big_quantity) {
                        //判断有效持币 和 持币算力
                        $MinePool = MinePool::query()->whereUserId($userId)->first();
                        if ($MinePool->active < $returnConf->hold || ($MinePool->hold_time_day * $MinePoolConfig->hold_daily_reward_ratio) < $returnConf->hold_power_ratio) {
                            $redis->hMSet('return:user:' . $parent->node_id, [$levelKey => 0, $levelKey . '_time' => time()]);
                        }

                    } else {
                        $remove = 0;
                    }
                }
                if ($remove) {
                    $redis->sRem('return:set', $parent->node_id);
                    $redis->hDel('return:user:', $parent->node_id);
                }
            }
        }
    }

    /**
     * 是否达标给钥匙
     * @return false
     * @throws \Throwable
     */
    public static function calculateKey()
    {
        $redis = Redis::getInstance();
        $key = ['1' => 'copper', '2' => 'silver', '3' => 'gold'];
        $userIds = $redis->sMembers('return:set');
        $configs = PowerConfReturn::query()->get()->keyBy('level');
        foreach ($userIds as $userId) {
            $returnInfo = $redis->hGetAll('return:user:' . $userId);
            foreach ($configs as $level => $config) {
                if (!empty($returnInfo['lv' . $level]) && ($returnInfo['lv' . $level . '_time']) + self::GET_KEY_SECOND <= time()) {
                    try {
                        //返点奖 大小区业绩
                        $MinePoolConfig = MinePoolConf::query()->first();
                        $parentMinMax = BonusService::getUserMinMaxAmount($userId);
//                            var_dump($parentMinMax);die;
                        $userReturnInfo = $redis->hGetAll('return:user:' . $userId);

                        $levelKey = 'lv' . $config->level;
                        if (!empty($userReturnInfo[$levelKey])) {
                            continue;
                        }
                        //判断是否有达标
                        if ($parentMinMax['min'] >= $config->small_quantity && $parentMinMax['max'] >= $config->big_quantity) {
                            //判断有效持币 和 持币算力
                            $MinePool = MinePool::query()->whereUserId($userId)->first();
                            if ($MinePool->active < $config->hold) {
                                return false;
                            }
                            if (($MinePool->hold_time_day * $MinePoolConfig->hold_daily_reward_ratio) < $config->hold_power_ratio) {
                                return false;
                            }
                        }

                        DB::beginTransaction();

                        //判断有没有未使用钥匙
                        PowerReturnKey::query()->where(['user_id' => $userId, 'status' => 'normal'])->update(['status' => 'invalid', 'updated_at' => Carbon::now()->toDateTimeString()]);
                        $PowerReturnKey = new PowerReturnKey();
                        $PowerReturnKey->created_at = Carbon::now()->toDateTimeString();
                        $PowerReturnKey->user_id = $userId;
                        $PowerReturnKey->type = $key[$level];
                        $PowerReturnKey->small_quantity = BonusService::getUserMinMaxAmount($userId)['min'] ?? 0;
                        $PowerReturnKey->save();
                        $redis->hMSet('return:user:' . $userId, ['lv' . $level => -1, 'lv' . $level . '_finished_at' => time()]);
                        DB::commit();
                    } catch (\Throwable $e) {
                        DB::rollback();
                        throw $e;
                    }

                }
            }
        }
    }


    /**
     * 計算燒熵
     * @param $myPower
     * @param $parentPower
     * @param $burnEntropyTimes
     * @return float
     */
    public static function calculateBurn($myPower, $parentPower, $burnEntropyTimes)
    {
        return min(bcmul($parentPower, $burnEntropyTimes, 10), $myPower);
    }

    /**
     * 个人总算力
     * @param $userid
     * @return string
     */
    public static function totalPower($userid)
    {

        $staticPower = MinePool::query()->where('user_id', $userid)->first();
        if (!$staticPower || $staticPower->active == 0) {
            return 0;
        }
        $dynamicPower = UserPower::query()->where('user_id', $userid)->first();
        $dynamicPower = isset($dynamicPower) ? bcadd($dynamicPower->slide, bcadd($dynamicPower->invite ?? 0, $dynamicPower->gravitation)) : 0;
        return bcadd($dynamicPower, bcadd($staticPower->power ?? 0, $staticPower->hold_time_power ?? 0), 2);//节点总算力
    }

    /**
     * 团队总算力
     * @param $userId
     * @return string
     */
    public static function totalTeamPower($userId)
    {
        $data = BonusService::getNodeUserChild($userId);
        $power = 0;
        foreach ($data as $user) {
            $power += PoolService::totalPower($user->id);;
        }
        return $power;
    }

    /**
     * 静态算力
     * @param $userid
     * @return array
     */
    public static function staticPower($userid)
    {

        $staticPower = MinePool::query()->where('user_id', $userid)->first();
        if (!$staticPower || $staticPower->active == 0) {
            return ['symmetric_power' => 0, 'hold_power' => 0, 'static_power' => 0];
        }
        return ['symmetric_power' => sprintf('%.2f', $staticPower->power), 'hold_power' => sprintf('%.2f', $staticPower->hold_time_power), 'static_power' => sprintf('%.2f', bcadd($staticPower->power, $staticPower->hold_time_power))];

    }

    /**
     * 各算力
     * @param $userid
     * @return array
     */
    public static function classifyPower($userid)
    {
        $staticPower = MinePool::query()->where('user_id', $userid)->first();
        if (!$staticPower || $staticPower->active == 0) {
            return ['symmetric_power' => 0, 'hold_power' => 0, 'gravitation_power' => 0, 'slide_power' => 0, 'direct_power' => 0, 'total_power' => 0];
        }
        $dynamicPowerInfo = UserPower::query()->where('user_id', $userid)->first();
        if (!$dynamicPowerInfo) {
            $dynamicPowerInfo = (object)['gravitation' => 0, 'slide' => 0, 'invite' => 0];
        }
        $data = ['symmetric_power' => $staticPower->power, 'hold_power' => $staticPower->hold_time_power, 'gravitation_power' => $dynamicPowerInfo->gravitation, 'slide_power' => $dynamicPowerInfo->slide, 'direct_power' => $dynamicPowerInfo->invite];

        $dynamicPower = isset($dynamicPowerInfo) ? bcadd($dynamicPowerInfo->slide, bcadd($dynamicPowerInfo->invite ?? 0, $dynamicPowerInfo->gravitation)) : 0;
        $data['total_power'] = sprintf('%.2f', bcadd($dynamicPower, bcadd($staticPower->power ?? 0, $staticPower->hold_time_power ?? 0), 2));//节点总算力
        return $data;
    }

    /**
     * teamNormal
     * @param $childOrUserId
     * @return array
     */
    public static function teamNormalMineNum($childOrUserId)
    {
        $child = is_array($childOrUserId) ? $childOrUserId : BonusService::getUserChild($childOrUserId);
        $count = 0;
        $staticPower = 0;
        foreach ($child as $level => $v) {
            $minePool = MinePool::query()->where('user_id', $v->id)->where('status', 'normal')->first();
            if ($minePool) {
                $count++;
                $staticPower += $minePool->power;
//                $staticPower += bcadd($minePool->power, $minePool->hold_time_power);
            }
        }
        return ['count' => $count, 'power' => sprintf('%.2f', $staticPower)];
    }

    public static function calculateUserCompete($parentMinMax, $returnConf, $MinePool)
    {
        $minePoolConf = MinePoolConf::query()->first();
        $standard = 0;
        if ($parentMinMax['min'] >= $returnConf->small_quantity && $parentMinMax['max'] >= $returnConf->big_quantity) {
            //判断有效持币 和 持币算力
            if ($MinePool->active >= $returnConf->hold && ($MinePool->hold_time_day * $minePoolConf->hold_daily_reward_ratio) >= $returnConf->hold_power_ratio) {
//                var_dump(1122);
                $standard = 1;
            }
        }
        return $standard;
    }

}
