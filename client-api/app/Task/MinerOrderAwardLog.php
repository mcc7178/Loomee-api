<?php

namespace App\Task;

use App\Logic\UserLogic;
use App\Logic\WalletLogic;
use App\Model\MinerConf;
use App\Model\MinerUserAwardOrder;
use App\Model\MinerUserOrder;
use App\Model\MinerUserOrderLog;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;

class MinerOrderAwardLog
{
    protected $signature = 'miner_order_award_log';
    
    protected $description = '定时任务计算矿机认购超过1000算力的剩余75%发放,如果为第一次发放则立即释放25%';
    /**
     * @Inject()
     * @var StdoutLoggerInterface
     */
    private $logger;
    
    public function handle()
    {
        try {
            DB::beginTransaction();
            //$param = $this->argument('a');
            $coin = 'fil';
            $date = date('Ymd');
            $time = time();
            $count = MinerUserAwardOrder::select('user_id')
                ->where('award_date', '=', $date)
                ->whereRaw('award_remain_day < award_day')
                ->count();
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
            $total = 2000;
            if (!empty($count)) {
                $page = ceil($count / $total); #计算总页面数
                /* 分段计算，防止数据太大 */
                $conf = MinerConf::query()->first()->toArray();
                for ($i = 1; $i <= $page; $i++) {
                    $dateUpdateId = [];//记录要修改日期的id，处理完统一+30天
                    $award = [];//统一处理要user_id奖励的金额
                    $orderLog = [];
                    $orderId = [];//依据order_id计算释放fil
                    $order = MinerUserAwardOrder::query()->select('id', 'order_num', 'award_day', 'award_remain_day', 'award_first', 'award_remain', 'user_id', 'power', 'type')
                        ->where('award_date', '=', $date)
                        ->whereRaw('award_remain_day < award_day')
                        ->forPage($i, $total)
                        ->get()->toArray();
                    foreach ($order as $key => $value) {
                        //查询用户是否设置分红
                        if ((new UserLogic())->isShare($value['user_id']) == 1) {
                            continue;
                        }
                        //释放收益 * 0.75系数，写死
                        $power = $value['power'];
                        //$power = bcmul($value['power'],'0.75',4);
                        /* $power * fil系数 * 全局fil系数 */
                        //$power    = bcmul($value['power'],$conf['hashrate_fil_proportion'],8);
                        //$power    = bcmul($power,$conf['hashrate_fil_award'],8);
                        $parentPower = 0;
                        $quantity = 0;
                        /* 如果为第一天奖励，立即释放25%*/
                        if ($value['award_remain_day'] == 0) {
                            $quantity = bcmul($power, $value['award_first'], 8);
                            if ($quantity > 0) {
                                $parentPower = bcadd($parentPower, $quantity, 8);
                                (new WalletLogic())->saveUserSymbolAsset($value['user_id'], $coin, $info[$value['type']], $quantity, $freeze = 0, $value['id'], $tips[$value['type']]);
                                /* 记录流水*/
                                MinerUserOrderLog::insert([
                                    'user_id' => $value['user_id'],
                                    'order_num' => $value['order_num'],
                                    'coin' => 'fil',
                                    'type' => $value['type'],
                                    'award_quantity' => $quantity,
                                    'date' => date('Ymd'),
                                    'created_at' => time()
                                ]);
                                //为类型2的需要计算父类算力奖励
                                if ($value['type'] == 2) {
                                    /* 记录释放和待释放 */
                                    MinerUserOrder::query()
                                        ->updateOrCreate(
                                            ['order_num' => $value['order_num']],
                                            [
                                                'release_fil_quantity' => DB::raw('release_fil_quantity +' . $quantity),
                                                'wait_release_fil_quantity' => DB::raw('wait_release_fil_quantity -' . $quantity)
                                            ]
                                        );
                                }
                            }
                        }
                        /* $power * 剩下的75% */
                        $power = bcmul($power, $value['award_remain'], 8);
                        /* $power / 180天  */
                        $power = bcdiv($power, $value['award_day'], 8);
                        $orderLog = [
                            'user_id' => $value['user_id'],
                            'order_num' => $value['order_num'],
                            'coin' => $coin,
                            'type' => $value['type'],
                            'award_quantity' => $power,
                            'date' => $date,
                            'created_at' => $time
                        ];
                        $parentPower = bcadd($parentPower, $power, 8);
                        // 75% 180天每天释放的
                        (new WalletLogic())->saveUserSymbolAsset($value['user_id'], $coin, $info[$value['type']], $power, $freeze = 0, $value['id'], $tips[$value['type']]);
                        MinerUserOrderLog::insert($orderLog);
                        //为类型2的需要计算父类算力奖励
                        if ($value['type'] == 2) {
                            /* 记录释放和待释放 */
                            MinerUserOrder::query()
                                ->updateOrCreate(
                                    ['order_num' => $value['order_num']],
                                    [
                                        'release_fil_quantity' => DB::raw('release_fil_quantity +' . $power),
                                        'wait_release_fil_quantity' => DB::raw('wait_release_fil_quantity -' . $power)
                                    ]
                                );
                        }
                        //记录需要修改天数的日期id
                        $dateUpdateId[] = $value['id'];
                        //为类型2的需要计算父类算力奖励
//                        if($value['type'] == 2){
//                            //查询一级父类，判断是否有购买矿机
//                            $parent = BonusService::getUserParent($value['user_id']);
//                            if(!empty($parent)) {
//                                //查询一级父类，A推荐B，A获得B收益的系列fil的10%
//                                foreach ($parent as $k => $v) {
//                                    if ($v->lvl == 0) {
//                                        //判断上级是否有购买记录，有的话才赠送5%推荐奖励
//                                        $miner = MinerUserOrder::query()->where('user_id', $v->from_uid)->first();
//                                        if (!empty($miner)) {
//                                            if ($conf['invite_reward'] > 0) {
//                                                // 计算0.75系数/180
//                                                //$qt = bcdiv($conf['hashrate_award_remain'], $conf['hashrate_award_day'], 8);
//                                                //计算(0.25 fil+0.75/180)
//                                                //$qt = bcadd($power, $quantity, 8);
//                                                //计算(0.25 fil+0.75/180)的结果 *10%
//                                                $parentPower = bcmul($parentPower, $conf['hashrate_proportion'], 8);
//                                                WalletLogic::saveUserSymbolAsset($v->from_uid, 'fil', 'miner_release_bonus', $parentPower, $freeze = 0, $value['id'],'矿机直推奖励');
//                                                // 写入miner矿机系列流水日志
//                                                $orderLog = [
//                                                    'user_id' => $v->from_uid,
//                                                    'order_num' => $value['order_num'],
//                                                    'coin' => 'fil',
//                                                    'type' => 4,
//                                                    'award_quantity' => $parentPower,
//                                                    'date' => date('Ymd'),
//                                                    'created_at' => time()
//                                                ];
//                                                //记录流水
//                                                MinerUserOrderLog::insert($orderLog);
//                                                // 记录释放和待释放
////                                                MinerUserOrder::query()
////                                                    ->updateOrCreate(
////                                                        ['order_num' => $value['order_num']],
////                                                        [
////                                                            'release_fil_quantity' => DB::raw('release_fil_quantity +' . $lqdQuantity),
////                                                            'wait_release_fil_quantity' => DB::raw('wait_release_fil_quantity -' . $lqdQuantity)
////                                                        ]
////                                                    );
//                                            }
//                                        }
//                                    }
//                                }
//                            }
//                        }
                    }
                    /* 批量修改下次奖励日期+30，奖励次数记录+1 */
                    MinerUserAwardOrder::query()->whereIn('id', $dateUpdateId)->update([
                        'award_date' => date('Ymd', strtotime('+1 days', $time)),
                        'award_remain_day' => DB::raw('award_remain_day + 1'),
                    ]);
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
}