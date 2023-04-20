<?php

namespace App\Task;

use App\Logic\MinerLogic;
use App\Model\MinerLevelConf;
use App\Model\MinerUserOrder;
use App\Model\MinerUserOrderLog;
use App\Model\User;
use App\Foundation\Facades\Log;
use App\Model\MinerUserOrderTeamAll;

// thy 新增矿机全网收益订单
class NewMinerAllBonusTask
{
    protected $signature = 'miner_all_bonus';

    protected $description = '定时任务计算矿机订单 全网收益';
    protected $accuracy = 8;   // 精确度
    protected $type = 6;    // 全网算力加权收益

    public function handle()
    {
        $date = date('Ymd', strtotime('-1 day'));

        /* 查询今天已生成记录则不再往下执行 */
        $is_exists = MinerUserOrderTeamAll::query()->where(['type' => $this->type,'date' => date('Ymd')])->first();
        if(!empty($is_exists)) return null;

        /* 结点配置 */
        $levelConf = MinerLevelConf::query()->where('id','>',1)->orderBy('community_performance', 'asc')->get()->toArray();
        $manage_fee = 0.02;
        $node       = 2;
        $big_node   = 3;
        $super_node = 4;

        /* 查询用户是否有认购过矿机，购买过才有资格分红 */
        $miner = MinerUserOrder::query()->select('user_id')->groupBy('user_id')->get()->pluck('user_id');
        if(empty($miner))   return null;

        $user = User::query()->select('miner_level_id', 'id')
            ->where('miner_level_id', '>', 1)
            ->where('is_share', 0)
            ->whereIn('id', $miner)
            ->get()
            ->toArray();
        if(empty($user))    return null;

        $user_list = array_column($user,'miner_level_id','id');

        // 当天全网算力
        $quantity_total = MinerUserOrderLog::query()->where('date', $date)->where('type', 2)->sum('award_quantity');
        $node_list = array_count_values($user_list);
        $param = [];
        try {
            $node_profit = 0;
            foreach ($levelConf as $key => $value) 
            {
                $quantity = 0; 
                $nums = 0;
                for($i=2;$i<5;$i++)
                {
                    if($value['id'] > $i)   continue;
                    $nums += $node_list[$i] ?? 0;
                }

                // 当前节点收益
                // $quantity = $quantity_total * $value['whole_network_computing_power'] * $manage_fee / $nums;
                $quantity = bcmul($quantity_total , $value['whole_network_computing_power'], $this->accuracy); 
                // $quantity = bcmul($quantity , (1-$manage_fee), $this->accuracy); 
                if($nums == 0)
                    $quantity = 0;
                else
                    $quantity = bcdiv($quantity , $nums, $this->accuracy); 

                $node_profit = bcadd($node_profit,$quantity, $this->accuracy);
                $param[$value['id']] = [
                    'quantity' => $node_profit,    // 当前节点应获得的收益
                    'perple_nums' => $nums,     // 当前节点人数 快照
                    'proportion' => $value['whole_network_computing_power']     // 当前节点收益比例 快照
                ];
            }
        
        } catch (\Throwable $ex) {
            $msg = $ex->getMessage() . $ex->getFile() . '--' . $ex->getLine();
            Log::debugLog()->error($msg);
        }

        foreach($user_list as $k=>$v)
        {
            try {
                $data = [
                    'order_num' => MinerLogic::generateOrderNumber($k.rand(1,10000)),
                    'user_id'           => $k,
                    'miner_level_id'    => $v,
                    'node_profit'       => $param[$node]['quantity'],
                    'big_node_profit'   => $param[$big_node]['quantity'],
                    'super_node_profit' => $param[$super_node]['quantity'],
                    'quantity'          => $quantity_total,
                    'date'              => date('Ymd'),
                    'award_date'        => date('Ymd'),
                    'award_remain_day'  => 0,
                    'manage_fee'        => $manage_fee,
                    'node_nums'         => json_encode($node_list),
                    'type'              => $this->type,
                    'created_at'        => time(),
                    'updated_at'        => time(),
                ];

                MinerUserOrderTeamAll::insert($data);
                    
            } catch (\Throwable $ex) {
                $msg = '全网算力加权收益 用户:'.$k.'=>'.$v.' || ';
                $msg .= $ex->getMessage() . $ex->getFile() . '--' . $ex->getLine();
                Log::debugLog()->error($msg);
            }
        }

    }



}