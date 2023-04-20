<?php

namespace App\Task;

use App\Logic\WalletLogic;
use App\Model\MinerConf;
use App\Model\MinerUserOrder;
use App\Model\User;
use App\Model\MinerUserOrderLog;
use Hyperf\DbConnection\Db;
use App\Foundation\Facades\Log;

// thy新的 用户挖矿订单每日结算处理
class MinerOrderSettlement
{
    protected $signature = 'miner_order';

    protected $description = '定时任务计算矿机认购订单奖励';
    protected $settlement = [];

    protected $hashrate_award_day     = 0;      //  180
    protected $hashrate_award_first   = 0;      //  0.25
    protected $hashrate_award_remain  = 0;      //  0.75

    protected $accuracy = 18;   // 精确度
    protected $rec_proportion = 0;    // 推荐人奖励 10%


    public function handle()
    {
        $info = [
            2 => 'miner_static_bonus',//'挖矿静态收益',
            4 => 'miner_release_bonus',//'挖矿直推奖励'
            5 => 'miner_team_bonus',//=>'挖矿团队加权收益',
            6 => 'miner_all_bonus',//=>'挖矿全网加权收益',
        ];
        $tips = [
            2 => '挖矿静态收益',
            4 => '挖矿直推奖励',
            5 => '挖矿团队加权收益',
            6 => '挖矿全网加权收益',
        ];

        $own_type = 2;
        $rec_type = 4;
        $coin = 'fil';
        $date = date('Ymd');
        $time = time();
        
        /* 分段计算，防止数据太大 */
        $conf = MinerConf::query()->first()->toArray();

        $order = MinerUserOrder::query()
            ->where('award_date', '=', $date)
            // ->where('status','<>','finished')
            ->get()->toArray();
   
        $this->hashrate_award_day     = $conf['hashrate_award_day'];        //  180
        $this->hashrate_award_first   = $conf['hashrate_award_first'];      //  0.25
        $this->hashrate_award_remain  = $conf['hashrate_award_remain'];     //  0.75
        $this->rec_proportion         = $conf['hashrate_proportion'];       //  直推算力 10%

            
        foreach ($order as $key => $value) 
        {
            $award_date = date_create($value['award_date']);
            $date       = date_create($value['date']);
            $day_ok_sub = $value['validity_period'] + $value['encapsulation_period'];  // 时间差
     
            $day_surplus = 0;
            //查询用户是否设置分红
            $my_info = User::query()->where('id',$value['user_id'])->first();
            if(empty($my_info)) continue;
            if ($my_info->is_share == 1) {
                continue;
            }

            $power = $value['power'];
            $manage_fee = $value['manage_fee'];

            $day_sub = abs(date_diff($date, $award_date)->format("%R%a"));  // 时间差
            if($value['status'] == 'finished' && $day_sub > ($this->hashrate_award_day + $day_ok_sub))   continue;

            // 计算当前时间对应的状态码
            $status_sign = '';
            if($value['status'] != 'finished')
            {
                if($day_sub < $value['encapsulation_period'])
                {
                    $status_sign = 'in_package';
                }
                elseif($day_sub < $day_ok_sub)
                {
                    $status_sign = 'processing';
                }
                else
                {
                    $status_sign = 'finished';
                }
            }
            
            if($day_sub == 0)    continue;   // 下单当天不会进行产出的

            // 计算收益比例
            if($value['status'] == 'in_package')
            {
                $period_profit = $value['encapsulation_period_profit'];     //  封装期收益
            }
            else
            {
                $period_profit = $value['full_calculation_profit'];     //  有效期收益
            }
        
            $real_day_c     = bcmul($power , $period_profit, $this->accuracy);       // 算出当天的日产出
            $real_day_cc    = bcmul($real_day_c, (1 - $manage_fee), $this->accuracy);   // 扣除平台服务费

            $day_base_profit    = bcmul($real_day_cc, $this->hashrate_award_first, $this->accuracy);      // 每天25%的基础收益
            $day_base_surplus   = bcmul($real_day_cc, $this->hashrate_award_remain, $this->accuracy);     // 75%的基础收益 
            
            // 下单后的第一天只结算当天收益的 25%
            if($day_sub == 1)
            {
                // 个人该矿机订单当天释放
                $day_surplus = $day_base_profit;
            }
            else
            {
                $day_c = $day_sub - 1;
                if(array_key_exists($day_c,$this->settlement))
                {
                    $settlement = $this->settlement[$day_c];
                }
                else
                {
                    $settlement = $this->getSettlementNums($day_c);
                }

                $surplus = $this->makeProfit($settlement,$day_base_surplus);

                
                // 个人该矿机订单当天释放
                if($value['status'] == 'finished' || $day_c > $this->hashrate_award_day)
                {
                    $day_surplus = $surplus;
                }
                else
                {
                    $day_surplus = bcadd($day_base_profit, $surplus, $this->accuracy);
                }
            }

            // 矿机订单当天产出的推荐人奖励
            $recommender_surplus = bcmul($day_surplus , $this->rec_proportion, $this->accuracy); 

            // Log::debugLog()->debug(' day:'.$day_sub);
            // Log::debugLog()->debug(' surplus:'.$surplus);
            // Log::debugLog()->debug(' day_surplus:'.$day_surplus);
            // Log::debugLog()->debug(' recommender_surplus:'.$recommender_surplus);
            if($day_surplus == 0)   continue;

            try {
                DB::beginTransaction();

                // 个人挖矿静态收益
                WalletLogic::saveUserSymbolAsset($value['user_id'], $coin, $info[$own_type], $day_surplus, $freeze = 0, $value['id'], $tips[$own_type]);
                // 添加 个人挖矿静态收益 记录
                MinerUserOrderLog::insert([
                    'user_id' => $value['user_id'],
                    'order_num' => $value['order_num'],
                    'coin' => 'fil',
                    'type' => $own_type,
                    'award_quantity' => $day_surplus,
                    'date' => date('Ymd'),
                    'created_at' => $time,
                    'manage_fee' => $manage_fee,
                ]);

                // 修改该订单的 已释放 和 待释放 数量
                if($status_sign != '')
                {
                    MinerUserOrder::query()
                        ->updateOrCreate(
                            ['order_num' => $value['order_num']],
                            [
                                'release_fil_quantity' => DB::raw('release_fil_quantity +' . $day_surplus),
                                'wait_release_fil_quantity' => DB::raw('wait_release_fil_quantity -' . $day_surplus),
                                'award_date' => date('Ymd', strtotime('+1 days', $time)),
                                'award_num_use' => DB::raw('award_num_use + 1'),
                                'status' => $status_sign,
                            ]
                        );
                }
                else
                {
                    MinerUserOrder::query()
                        ->updateOrCreate(
                            ['order_num' => $value['order_num']],
                            [
                                'release_fil_quantity' => DB::raw('release_fil_quantity +' . $day_surplus),
                                'wait_release_fil_quantity' => DB::raw('wait_release_fil_quantity -' . $day_surplus),
                                'award_date' => date('Ymd', strtotime('+1 days', $time)),
                                'award_num_use' => DB::raw('award_num_use + 1'),
                            ]
                        );
                }
                
                // 添加推荐人的收益
                if($my_info->from_uid != 0)
                {
                    $parent = $this->getParent($my_info->from_uid);
                    $parent_miner = MinerUserOrder::query()->where('user_id',$my_info->from_uid)->where('status','finished')->first();
                    if(!empty($parent) && !empty($parent_miner))
                    {
                        WalletLogic::saveUserSymbolAsset($my_info->from_uid, $coin, $info[$rec_type], $recommender_surplus, $freeze = 0, $value['id'], $tips[$rec_type]);
                        // 添加记录
                        MinerUserOrderLog::insert([
                            'user_id' => $my_info->from_uid,
                            'order_num' => $value['order_num'],
                            'coin' => 'fil',
                            'type' => $rec_type,
                            'award_quantity' => $recommender_surplus,
                            'date' => date('Ymd'),
                            'created_at' => $time,
                            'manage_fee' => $manage_fee,
                        ]);
                    }
                }

                // 质押退还
                if(($value['status'] == 'finished' || $status_sign == 'finished') && $day_ok_sub == $day_sub)
                {
                    $pay_pledge_t = bcmul($value['pledge_t'] , $value['pledge_return_ratio'], $this->accuracy);  ;
                    WalletLogic::saveUserSymbolAsset($value['user_id'], $coin, 'miner_pledge_t', $pay_pledge_t, $freeze = 0, $value['id'], '矿机质押返还');
                }

                DB::commit();
            } catch (\Throwable $ex) {
                DB::rollback();
                $msg = $ex->getMessage() . $ex->getFile() . '--' . $ex->getLine();
                Log::debugLog()->error($msg);
            }

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
    protected function makeProfit($settlement,$day_base_surplus)
    {
        $surplus = 0;
        foreach($settlement as $k=>$v)
        {
            $next_houxu_get = bcmul((string)$day_base_surplus, (string)number_format($v, 0, '.', ''), $this->accuracy);
            $c_value = 0;
            $shouyi = 0;
            for($i=1;$i<=$k;$i++)
            {
                $c_value        = bcdiv($next_houxu_get,$this->hashrate_award_day,$this->accuracy);
                $next_houxu_get = bcmul($c_value, $this->hashrate_award_remain, $this->accuracy);
            }
            
            $shouyi = bcmul($c_value, $this->hashrate_award_first, $this->accuracy);
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