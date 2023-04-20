<?php

namespace App\Task;

use App\Logic\WalletLogic;
use App\Model\MinerConf;
use App\Model\MinerUserOrder;
use App\Model\User;
use App\Model\MinerUserOrderLog;
use Hyperf\DbConnection\Db;
use App\Foundation\Facades\Log;
use App\Model\MinerUserOrderTeamAll;

// thy新的 挖矿订单 团队和全网收益的日订单表结算
class NewMinerOrderTeamAllProfit
{
    protected $signature = 'miner_order_team_all_profit';

    protected $description = '团队和全网收益的日订单表结算';
    protected $settlement = [];

    protected $hashrate_award_day     = 0;      //  180
    protected $hashrate_award_first   = 0;      //  0.25
    protected $hashrate_award_remain  = 0;      //  0.75

    protected $accuracy = 18;   // 精确度


    public function handle()
    {
        $info = [
            5 => 'miner_team_bonus',//=>'挖矿团队加权收益',
            6 => 'miner_all_bonus',//=>'挖矿全网加权收益',
        ];
        $tips = [
            5 => '挖矿团队加权收益',
            6 => '挖矿全网加权收益',
        ];

        $coin = 'fil';
        $date = date('Ymd');
        $time = time();
        
        /* 分段计算，防止数据太大 */
        $conf = MinerConf::query()->first()->toArray();

        $this->hashrate_award_day     = $conf['hashrate_award_day'] ?? 180;        //  180
        $this->hashrate_award_first   = $conf['hashrate_award_first'] ?? 0.25;      //  0.25
        $this->hashrate_award_remain  = $conf['hashrate_award_remain'] ?? 0.75;     //  0.75
        
        $order = MinerUserOrderTeamAll::query()
            ->where('award_date', '=', $date)
            ->where('award_remain_day', '!=', $this->hashrate_award_day)
            ->get()->toArray();
            
        $node = [2=>'node_profit',3=>'big_node_profit',4=>'super_node_profit'];

        foreach ($order as $key => $value) 
        {
            $day_surplus = 0;
            //查询用户是否设置分红
            $my_info = User::query()->where('id',$value['user_id'])->first();
            if(empty($my_info)) continue;
            if ($my_info->is_share == 1) {
                continue;
            }

            $own_miner = MinerUserOrder::query()->where('user_id',$value['user_id'])->where('status','finished')->first();
            if(empty($own_miner))   continue;

            $power = $value[$node[$value['miner_level_id']]];
            $manage_fee = $value['manage_fee'];

            $award_date = date_create($value['award_date']);
            $date       = date_create($value['date']);
            $day_sub    = abs(date_diff($date, $award_date)->format("%R%a"));  // 时间差

            // 计算收益比例
            $real_day_cc        = bcdiv($power, $this->hashrate_award_day, $this->accuracy);   // 按180天分
            $day_base_profit    = bcmul($real_day_cc, $this->hashrate_award_first, $this->accuracy);      // 每天25%的基础收益
            $day_base_surplus   = bcmul($real_day_cc, $this->hashrate_award_remain, $this->accuracy);     // 75%的基础收益 
            
            // 下单后的第一天只结算当天收益的 25%
            if($day_sub == 0)
            {
                // 个人该矿机订单当天释放
                $day_surplus = $day_base_profit;
            }
            else
            {
                if(array_key_exists($day_sub,$this->settlement))
                {
                    $settlement = $this->settlement[$day_sub];
                }
                else
                {
                    $settlement = $this->getSettlementNums($day_sub);
                }
                
                $surplus = $this->makeProfit($settlement,$day_base_surplus,$manage_fee);

                // 个人该矿机订单当天释放
                if($day_sub < $this->hashrate_award_day)
                    $day_surplus = bcadd($day_base_profit, $surplus, $this->accuracy);
                else
                    $day_surplus = $surplus;
            }
            
            $day_surplus = bcmul($day_surplus, (1 - $manage_fee), $this->accuracy);   // 扣除平台服务费

            // Log::debugLog()->debug(' day:'.$day_sub);
            // Log::debugLog()->debug(' surplus:'.$surplus);
            // Log::debugLog()->debug(' settlement:'.json_encode($settlement));
            // Log::debugLog()->debug(' day_base_surplus:'.$day_base_surplus);
            // Log::debugLog()->debug(' day_surplus:'.$day_surplus);
            
            try {
                DB::beginTransaction();

                // 个人挖矿静态收益
                WalletLogic::saveUserSymbolAsset($value['user_id'], $coin, $info[$value['type']], $day_surplus, $freeze = 0, $value['id'], $tips[$value['type']]);
                // 添加 个人挖矿静态收益 记录
                MinerUserOrderLog::insert([
                    'user_id' => $value['user_id'],
                    'order_num' => $value['order_num'],
                    'coin' => 'fil',
                    'type' => $value['type'],
                    'award_quantity' => $day_surplus,
                    'date' => date('Ymd'),
                    'created_at' => $time,
                    'manage_fee' => $manage_fee,
                ]);

                // 修改该订单的 已释放 和 待释放 数量
                MinerUserOrderTeamAll::query()
                ->updateOrCreate(
                    ['order_num' => $value['order_num']],
                    [
                        'award_date' => date('Ymd', strtotime('+1 days', $time)),
                        'award_remain_day' => DB::raw('award_remain_day + 1'),
                        'updated_at' => time(),
                    ]
                );
                
                DB::commit();
            } catch (\Throwable $ex) {
                DB::rollback();
                $msg = $ex->getMessage() . $ex->getFile() . '--' . $ex->getLine();
                Log::debugLog()->error($msg);
            }

            // return null;
        }
    }

    /**
     * 查询一级父级
     */
    protected function getParent($user_id)
    {
        return  User::query()->where('id',$user_id)->first();
    }

    /**
     * 收益计算
     */
    protected function makeProfit($settlement,$day_base_surplus,$manage_fee)
    {
        $surplus = 0;
        foreach($settlement as $k=>$v)
        {
            $next_houxu_get = bcmul($day_base_surplus, number_format($v, 0, '.', ''), $this->accuracy);
            $c_value = 0;
            $shouyi = 0;
            for($i=1;$i<=$k;$i++)
            {
                $c_value        = bcdiv($next_houxu_get, $this->hashrate_award_day,$this->accuracy);
                $next_houxu_get = bcmul($c_value, $this->hashrate_award_remain, $this->accuracy);
            }
            
            $shouyi  = bcmul($c_value, $this->hashrate_award_first, $this->accuracy);
            $surplus = bcadd($surplus, $shouyi, $this->accuracy);
        }

        return $surplus;
    }


    /**
     * 计算指定时间的结算次数
     */
    protected function getSettlementNums($value)
    {
        $res = [];
        for($i = 1; $i <= $value; $i++)
        {
            if(empty($res))
            {
                $res[1] = 1;
            }
            else
            {
                $new[1] = 1;
                foreach($res as $k=>$v)
                {
                    $new[$k+1] = $v;
                }

                $re_new = [];
                foreach($new as $ka=>$va)
                {
                    if(!array_key_exists(($ka+1),$new))
                        $re_new[$ka] = $new[$ka];
                    else
                        $re_new[$ka] = $new[$ka] + $new[$ka+1];
                }

                $res = $re_new;
            }
        }

        if($value > $this->hashrate_award_day)
        {
            $cu = $this->hashrate_award_day - $value;
            for($j=1;$j<=$cu;$j++)
            {
                unset($res[$j]);
            }
        }

        $this->settlement[$value] = $res;
        return $res;
    }



















}