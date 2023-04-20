<?php

namespace App\Task;

use App\Logic\UserLogic;
use App\Model\Miner;
use App\Model\MinerConf;
use App\Model\MinerUserAwardOrder;
use App\Model\MinerUserOrder;
use App\Services\BonusService;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\DbConnection\Db;

class MinerOrderAward
{
    protected $signature = 'miner_order_award';
    
    protected $description = '定时任务计算矿机认购每天收益订单';
    
    /**
     * @Inject()
     * @var StdoutLoggerInterface
     */
    private $logger;
    
    public function handle()
    {
        try {
            var_dump("计算xxxx");
            DB::beginTransaction();
            $awardDate = date('Ymd');
            // $date = date('Ymd', strtotime('-1 days'));
            $date = date('Ymd');
            $time = time();
            $conf = MinerConf::query()->first()->toArray();
            MinerUserOrder::query()
                ->where('status', '!=', 'finished')
                //->where('award_date','=',$date)
                ->whereRaw('award_num_use < award_num')
                ->chunkById(1000, function ($data) use ($conf, $date, $time, $awardDate) {
                    foreach ($data as $item) {
                        //查询用户是否设置分红
                        if ((new UserLogic())->isShare($item->user_id) == 1) {
                            continue;
                        }
                        $minerConf = Miner::query()->where('id', $item->miner_id)->first();
                        //默认按挖矿期计算收益, 如果下面判断是封装期，会计算成封装期收益
                        //$power = $item->full_calculation_profit;//按照订单快照记录的收益
                        $power = $minerConf->full_calculation_profit;//跟随矿机修改的收益
                        if ($item->status == 'in_package') {
                            //如果是封装期按封装期比例计算
                            //$power = $item->encapsulation_period_profit;//按照订单快照记录的收益
                            $power = $minerConf->encapsulation_period_profit;//跟随矿机修改的收益
                            //如果下单时间+封装期时间 》 当前时间， 则修改为已挖矿期
                            $packageData = strtotime('+ ' . $item->encapsulation_period . ' days', strtotime(date($item->date)));
                            //封装期已过，修改状态为挖矿期
                            if ($time >= $packageData) {
                                $item->status = 'processing';
                                $item->save();
                            }
                        } else if ($item->status == 'processing') {
                            //如果下单时间+封装期时间+有效期时间 》 当前时间， 则修改为订单已完成
                            $finishTime = bcadd($minerConf->encapsulation_period, $item->validity_period, 0);
                            $packageData = strtotime('+ ' . $finishTime . ' days', strtotime(date($item->date)));
                            //封装期已过，修改状态为挖矿期
                            if ($time >= $packageData) {
                                $item->status = 'finished';
                                $item->save();
                                //已完成订单不在生成收益
                                continue;
                            }
                        }
                        $miner = Miner::query()->where('id', $item->miner_id)->first();
                        //平台管理费
                        $charge = $miner->manage_fee ?? $item->manage_fee;//矿机产品管理费变动优先，若不存在取订单存储的管理费
                        //管理费
                        $manageCharge = bcmul($power, $charge, 8);
                        //减去管理费
                        $quantity = bcsub($power, $manageCharge, 8);
                        //最后的收益 * 购买台数
                        $quantity = bcmul($quantity, $item['power'], 8);
                        //计算出的fil收益，25立即释放比例，75%按180天释放比例
                        //如果是挖矿期，按挖矿期比例计算
                        // 算力 * fil系数 * 全局fil系数
                        //$total    = bcmul($power, $conf['hashrate_fil_proportion'],8);
                        //$fil      = bcmul($total, $conf['hashrate_fil_award'],8);
                        //释放收益 * 0.75系数，写死
                        $quantity = bcmul($quantity, $conf['static_bobi'], 8);//,$conf['static_bobi']静态波比系数
                        $awardOrder = [
                            'user_id' => $item['user_id'],
                            'order_num' => $item['order_num'],
                            'power' => $quantity,
                            'award_first' => $conf['hashrate_award_first'],
                            'award_remain' => $conf['hashrate_award_remain'],
                            'award_day' => $conf['hashrate_award_day'],//$item->validity_period,
                            'award_remain_day' => 0,
                            'date' => $date,
                            'award_date' => date('Ymd'),//有效期天数
                            'created_at' => $time,
                            'updated_at' => $time,
                            'manage_fee' => $miner->manage_fee,//平台手续费快照
                            'type' => 2,
                        ];
                        
                        // 批量新增矿机收益订单
                        MinerUserAwardOrder::insert($awardOrder);
                        $item->wait_release_fil_quantity = DB::raw('wait_release_fil_quantity +' . $quantity);
                        $item->manage_fee = $miner->manage_fee;
                        $item->save();
                        
                        //查询父类是否满足直推资格
                        $parent = (new BonusService())->getUserParent($item['user_id'], 1);
                        if (!empty($parent)) {
                            //查询一级父类，A推荐B，A获得B收益的系列fil的10%
                            foreach ($parent as $k => $v) {
                                if ($v->lvl == 0) {
                                    //判断上级是否有购买记录，有的话才生成推荐奖励180天的订单
                                    $miner = MinerUserOrder::query()->where('user_id', $v->from_uid)->first();
                                    if (!empty($miner)) {
                                        //本次收益 * 直推奖励系数
                                        $quantity = bcmul($quantity, $conf['hashrate_proportion'], 8);
                                        $recommendAwardOrder = [
                                            'user_id' => $v->from_uid,
                                            'order_num' => $item['order_num'],
                                            'power' => $quantity,
                                            'award_first' => $conf['hashrate_award_first'],
                                            'award_remain' => $conf['hashrate_award_remain'],
                                            'award_day' => $conf['hashrate_award_day'],
                                            'award_remain_day' => 0,
                                            'date' => $date,
                                            'award_date' => date('Ymd'),//有效期天数
                                            'created_at' => $time,
                                            'updated_at' => $time,
                                            'manage_fee' => $miner->manage_fee,//平台手续费快照
                                            'type' => 4,
                                        ];
                                        // 新增直推收益的矿机收益订单
                                        MinerUserAwardOrder::insert($recommendAwardOrder);
                                        //$parentPower = bcmul($parentPower, $conf['hashrate_proportion'], 8);
                                        //WalletLogic::saveUserSymbolAsset($v->from_uid, 'fil', 'miner_release_bonus', $parentPower, $freeze = 0, $value['id'],'矿机直推奖励');
                                        // 写入miner矿机系列流水日志
                                        /*$orderLog = [
                                            'user_id' => $v->from_uid,
                                            'order_num' => $value['order_num'],
                                            'coin' => 'fil',
                                            'type' => 4,
                                            'award_quantity' => $parentPower,
                                            'date' => date('Ymd'),
                                            'created_at' => time()
                                        ];
                                        //记录流水
                                        MinerUserOrderLog::insert($orderLog);*/
                                    }
                                }
                            }
                        }
                    }
                });
            DB::commit();
        } catch (\Throwable $ex) {
            DB::rollback();
            $msg = $ex->getMessage() . $ex->getFile() . '--' . $ex->getLine();
            $this->logger->error($msg);
            var_dump($msg);
        }
    }
    
    public function handle1()
    {
        try {
            //$param = $this->argument('a');
            $coin = 'fil';
            $date = date('Ymd');
            $awardDate = date('Ymd', strtotime("+90 days"));
            $time = time();
            $orderId = [];//依据order_id计算释放fil
            DB::beginTransaction();
            $count = MinerUserOrder::select('user_id')
                ->select('id', 'user_id', 'power', 'order_num')
                ->where('power', '>', 0)
                ->count();
            $total = 2000;
            if (!empty($count)) {
                $page = ceil($count / $total); #计算总页面数
                $conf = MinerConf::query()->first()->toArray();
                for ($i = 1; $i <= $page; $i++) {
                    $order = MinerUserOrder::query()
                        ->select('id', 'user_id', 'power', 'order_num')
                        ->where('power', '>', 0)
                        ->forPage($i, $total)
                        ->get()->toArray();
                    $log = [];//流水日志
                    $awardOrder = [];//收益订单
                    $user = [];//用户及时25%收益
                    
                    foreach ($order as $k => $v) {
                        if (empty($award[$v['user_id']])) {
                            $user[$v['user_id']] = 0;
                        }
                        if (empty($orderId[$v['id']])) {
                            $orderId[$v['id']]['fil'] = 0;
                            $orderId[$v['id']]['wait_fil'] = 0;
                        }
                        // $power * fil系数 * 全局fil系数
                        $total = bcmul($v['power'], $conf['hashrate_fil_proportion'], 8);
                        $power = bcmul($total, $conf['hashrate_fil_award'], 8);
                        // $power * 25%
                        $quantity = bcmul($power, $conf['hashrate_award_first'], 8);
                        $awardOrder[] = [
                            'user_id' => $v['user_id'],
                            'order_num' => $v['order_num'],
                            'power' => $v['power'],
                            'award_first' => $conf['hashrate_award_first'],
                            'award_remain' => $conf['hashrate_award_remain'],
                            'award_day' => $conf['hashrate_award_day'],
                            'award_remain_day' => 0,
                            'date' => $date,
                            'award_date' => $awardDate,
                            'created_at' => $time,
                            'updated_at' => $time,
                        ];
                        /*$log[] = [
                            'user_id'            => $v['user_id'],
                            'order_num'          => $v['order_num'],
                            'coin'               => $coin,
                            'award_quantity'     => $quantity,
                            'date'               => $date,
                            'created_at'         => $time
                        ];*/
                        // 25%收益立即到账
                        //$user[$v['user_id']]     = $quantity;
                        //记录fil已释放，25%立即释放，剩下75%为待释放
                        //$orderId[$v['id']]['fil'] = $quantity;
                        //$orderId[$v['id']]['wait_fil'] = bcsub($total, $quantity,8);
                        $orderId[$v['id']]['wait_fil'] = $power;
                    }
                    // 批量新增矿机收益订单
                    MinerUserAwardOrder::insert($awardOrder);
                    // 批量新增流水记录
                    //MinerUserOrderLog::insert($log);
                    foreach ($orderId as $k => $v) {
                        MinerUserOrder::query()
                            ->updateOrCreate(
                                ['id' => $k],
                                [
                                    'wait_release_fil_quantity' => DB::raw('wait_release_fil_quantity +' . $v['wait_fil']),
                                    //'release_fil_quantity'      =>DB::raw('release_fil_quantity +'.$v['fil'])
                                ]
                            );
                    }
                }
            }
            DB::commit();
        } catch (\Throwable $ex) {
            DB::rollback();
            $msg = $ex->getMessage() . $ex->getFile() . '--' . $ex->getLine();
            $this->logger->error($msg);
            var_dump($msg);
        }
    }
    
    public function generateOrderNumber(int $id): string
    {
        return mt_rand(10, 99)
            . sprintf('%010d', time() - 946656000)
            . sprintf('%03d', (float)microtime() * 1000)
            . sprintf('%03d', (int)$id % 1000);
    }
}