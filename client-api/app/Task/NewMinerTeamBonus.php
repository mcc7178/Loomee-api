<?php

namespace App\Task;

use App\Logic\MinerLogic;
use App\Model\MinerConf;
use App\Model\MinerLevelConf;
use App\Model\MinerUserAwardOrder;
use App\Model\MinerUserOrder;
use App\Model\MinerUserOrderLog;
use App\Model\User;
use Hyperf\DbConnection\Db;
use App\Foundation\Facades\Log;
use App\Model\MinerUserOrderTeamAll;
use App\Services\BonusService;
use App\Utils\Redis;

// thy 新增矿机团队收益订单
class NewMinerTeamBonus
{
    protected $signature = 'miner_all_bonus';

    protected $description = '定时任务计算矿机订单 团队收益';
    protected $user_list = [];
    protected $minerConfig = [];
    protected $levelConf = [];
    protected $accuracy = 8;   // 精确度
    protected $type = 5;    // 团队算力加权收益

    protected $manage_fee = 0.02;
    protected $node       = 2;
    protected $big_node   = 3;
    protected $super_node = 4;

    public function handle()
    {
        $date = date('Ymd', strtotime('-1 day'));

        /* 查询今天已生成记录则不再往下执行 */
        $is_exists = MinerUserOrderTeamAll::query()->where(['type' => $this->type,'date' => date('Ymd')])->first();
        if(!empty($is_exists)) return null;

        /* 结点配置 */
        $this->minerConfig = MinerConf::query()->first()->toArray();
        $this->levelConf = MinerLevelConf::query()->where('id','>',1)->orderBy('community_performance', 'asc')->get()->toArray();
     
        /* 查询用户是否有认购过矿机，购买过才有资格分红 */
        $miner = MinerUserOrder::query()->select('user_id')->groupBy('user_id')->get()->pluck('user_id')->toArray();
        //查询此用户是否有静态收益
        $miner_p = MinerUserOrderLog::query()->select('user_id')->where('date', $date)->where('type', 2)->groupBy('user_id')->get()->pluck('user_id')->toArray();
        $user_filter = array_unique(array_merge($miner,$miner_p));
     
        if(empty($user_filter))   return null;

        $user = User::query()->select('miner_level_id', 'id')
            ->where('miner_level_id', '>', 1)
            ->where('is_share', 0)
            ->whereIn('id', $user_filter)
            ->get()
            ->toArray();
        if(empty($user))    return null;

        $this->user_list = array_column($user,'miner_level_id','id');

        // 当天全网算力
        $this->quantity_total = MinerUserOrderLog::query()->where('date', $date)->where('type', 2)->sum('award_quantity');

        foreach($user as $k=>$v)
        {
            try{
                $this->makeNodeOrder($v['id']);
            }
            catch (\Throwable $ex) {
                $msg = $ex->getMessage() . $ex->getFile() . '--' . $ex->getLine();
                Log::debugLog()->error($msg);
            }
        }
        
    }



    /**
     * 记录矿机全网收益订单
     */
    protected function makeNodeOrder($uid)
    {
        $parent = $this->getUserParent($uid);
        Log::debugLog()->debug('parent:'.json_encode($parent));
        // 获取团队业绩
        $userTeamPerformance = BonusService::getUserMinMaxAmount($uid);
        $quantity_total = bcadd($userTeamPerformance['min'], $userTeamPerformance['max'], 4);
        if($quantity_total == 0)    return null;
        // $quantity_total = 100;

        $node_list = array_count_values($parent);

        $param = [];
        try {
            $node_profit = 0;
            foreach ($this->levelConf as $key => $value) 
            {
                $quantity = 0; 
                $nums = 0;
                for($i=2;$i<5;$i++)
                {
                    if($value['id'] > $i)   continue;
                    $nums += $node_list[$i] ?? 0;
                }

                // 当前节点收益
                $quantity = bcmul($quantity_total , $value['hash_rate_bonus'], $this->accuracy); 
                // $quantity = bcmul($quantity , (1-$manage_fee), $this->accuracy); 
                if($nums == 0)
                    $quantity = 0;
                else
                    $quantity = bcdiv($quantity , $nums, $this->accuracy); 

                $node_profit = bcadd($node_profit,$quantity, $this->accuracy);
                $param[$value['id']] = [
                    'quantity' => $node_profit,    // 当前节点应获得的收益
                    'perple_nums' => $nums,     // 当前节点人数 快照
                    'proportion' => $value['hash_rate_bonus']     // 当前节点收益比例 快照
                ];
            }
        } catch (\Throwable $ex) {
            $msg = $ex->getMessage() . $ex->getFile() . '--' . $ex->getLine();
            Log::debugLog()->error($msg);
        }

        foreach($parent as $k=>$v)
        {
            try {
                $data = [
                    'order_num' => MinerLogic::generateOrderNumber($k.rand(1,10000)),
                    'user_id'           => $k,
                    'miner_level_id'    => $v,
                    'node_profit'       => $param[$this->node]['quantity'],
                    'big_node_profit'   => $param[$this->big_node]['quantity'],
                    'super_node_profit' => $param[$this->super_node]['quantity'],
                    'quantity'          => $quantity_total,
                    'date'              => date('Ymd'),
                    'award_date'        => date('Ymd'),
                    'award_remain_day'  => 0,
                    'manage_fee'        => $this->manage_fee,
                    'node_nums'         => json_encode($node_list),
                    'type'              => $this->type,
                    'created_at'        => time(),
                    'updated_at'        => time(),
                ];

                MinerUserOrderTeamAll::insert($data);
                
            } catch (\Throwable $ex) {
                $msg = '团队算力加权收益 用户:'.$k.'=>'.$v.' || ';
                $msg .= $ex->getMessage() . $ex->getFile() . '--' . $ex->getLine();
                Log::debugLog()->error($msg);
            }
        }

        Log::debugLog()->debug('parent:'.json_encode($parent));
        Log::debugLog()->debug('data:'.json_encode($data));
    }


    /**
     * 获取所有的上级
     * @param $node_id
     * @param int $lvl
     * @return mixed
     */
    public function getUserParent($uid)
    {
        $key = 'userParentData_mineruse_' . $uid;
        $redis = Redis::getInstance();
        $data = [];
        if ($redis->exists($key)) 
        {
            $list = json_decode($redis->get($key));
        } 
        else 
        {
            $info = BonusService::getUserParent($uid);
            $list = [];
            foreach ($info as $k => $v) 
            {
                $list[] =$v->id;
            }

            $redis->set($key, json_encode($list));
        }

        $uList = array_keys($this->user_list);
        foreach ($list as $k => $v) 
        {
            if(!in_array($v,$uList))  continue;

            $data[$v] = $this->user_list[$v];
        }

        return $data;
    }









}