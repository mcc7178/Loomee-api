<?php
namespace App\Task;

use App\Logic\UserLogic;
use App\Model\SubscribeOrder;
use App\Model\User;
use App\Model\UserProfitRank;
use App\Model\UserSubAssess;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Crontab\Annotation\Crontab;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Utils\ApplicationContext;
use App\Foundation\Common\RedisService;
use App\Foundation\Facades\Log;


class UserV5RankUpLevelTask
{
    /**
     * @Inject()
     * @var \Hyperf\Contract\StdoutLoggerInterface
     */
    private $logger;

    public function execute()
    {
        var_dump("开始升级处理".date('Y-m-d H:i:s'));

        $assess = Db::table("user_sub_rank_assess")->first();
        Log::debugLog()->debug('assess:'.json_encode($assess));
        if(!empty($assess)){

            $whitelist = Db::table("user")->where('is_node_whitelist',1)->where('subscribe_node_id','>',1)->pluck('subscribe_node_id','id')->toArray();
            if(!empty($whitelist))
            {
                $white_user = array_keys($whitelist);
                $white_nums = array_count_values($whitelist);
            }
            else
            {
                $white_user = [];
                $white_nums = [];
            }
            
            // $redis = ApplicationContext::getContainer()->get(\Hyperf\Redis\Redis::class);
            $redis = RedisService::getInstance('sub_user');
            var_dump("开始--处理".date('Y-m-d H:i:s'));
//            $month  = date('Y-m',strtotime("-1 month"));//获取当月
            $month  = date('Y-m', strtotime( date('Y-m') ) -86400 );//获取当月
            // $month  = date('Y-m', strtotime( date('Y-m') ) );//获取当月

            $data = $redis->hGet('user_add_month', $month);
            $data = json_decode($data, true) ?? [];

            $startLvl = $assess->level;
            $stopLvl = $assess->rank;

            $rankData = [];
            $rank = 1;

            array_multisort(array_column($data,'order'),SORT_DESC,$data);

            if(!empty($white_nums))    $stopLvl -= array_sum($white_nums);

            foreach ($data as $k => $item) {
                if(in_array($item['user_id'],$white_user))  continue;
                if ($item['subscribe_team_id'] >= $startLvl && $stopLvl >= $rank )
                {
                    $item['rank'] = $rank;
                    $item['is_set'] = 0;
                    $rankData[] = $item;
                    $rank ++ ;
                }
            }
            
            $over_flow = 0;
            $up_big_node = $assess->up_big_node;
            $upgradeUserData = [];
            
            if(array_key_exists(4,$white_nums))
            {
                if($up_big_node > $white_nums[4])
                {
                    $up_big_node -= $white_nums[4];
                }
                else
                {
                    $over_flow += $white_nums[4] - $up_big_node;
                    $up_big_node = 0;
                }
            }
            
            // V7 升级超级节点
            if ($up_big_node > 0)
            {
                // 前多少V7 成为 超级节点
                foreach ($rankData as $k => $rankDatum) {
                    if ($rankDatum['subscribe_team_id'] == 8 && $rankDatum['rank'] <= $up_big_node)
                    {
                        if ($rankDatum['is_node_whitelist'] == 0)
                        {
                            $upgradeUserData[] = [
                                'user_id' => $rankDatum['user_id'],
                                'subscribe_node_id' => 4
                            ];
                            $rankData[$k]['is_set'] = 1;
                        }
                    }
                }
            }

            // V6 升级 大节点
            $up_node_num = $assess->up_node_num;
            
            if($over_flow > 0)
            {
                $up_node_num -= $over_flow;
            }
            if(array_key_exists(3,$white_nums))
            {
                if($up_node_num > $white_nums[3])
                {
                    $up_node_num -= $white_nums[3];
                }
                else
                {
                    $over_flow += $white_nums[3] - $up_node_num;
                    $up_node_num = 0;
                }
            }

            if ($up_node_num > 0)
            {
                $up_node_num_a = 0;

                foreach ($rankData as $k => $rankDatum) {
                    if ($rankDatum['subscribe_team_id'] >= 7 && $up_node_num_a < $up_node_num)
                    {
                        if ($rankDatum['is_set'] == 0 && $rankDatum['is_node_whitelist'] == 0)
                        {
                            $upgradeUserData[] = [
                                'user_id' => $rankDatum['user_id'],
                                'subscribe_node_id' => 3
                            ];
                            $rankData[$k]['is_set'] = 1;
                            $up_node_num_a ++ ;
                        }
                    }
                }
            }

            // 升级 V5
            $down_node_num = $assess->down_node_num;
            if ($down_node_num > 0)
            {
                $down_node_num_a = 0;
                foreach ($rankData as $rankDatum) 
                {
                    if ($down_node_num_a < $down_node_num)
                    {
                        if ($rankDatum['is_set'] == 0 && $rankDatum['is_node_whitelist'] == 0)
                        {
                            $upgradeUserData[] = [
                                'user_id' => $rankDatum['user_id'],
                                'subscribe_node_id' => 2
                            ];
                        }
                    }
                }
            }
         
            if (!empty($upgradeUserData))
            {
                foreach ($upgradeUserData as $v) {
                    User::query()
                        ->where('id', $v['user_id'])
                        ->update([
                            'subscribe_node_id' => $v['subscribe_node_id']
                        ]);

                    Db::table('user_upgrade_node_level')
                        ->insert([
                            'level_id' => $v['subscribe_node_id'],
                            'user_id' => $v['user_id'],
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                }
            }
        }
    }
}
