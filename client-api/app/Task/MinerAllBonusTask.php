<?php

namespace App\Task;

use App\Logic\MinerLogic;
use App\Model\MinerConf;
use App\Model\MinerLevelConf;
use App\Model\MinerUserAwardOrder;
use App\Model\MinerUserOrder;
use App\Model\MinerUserOrderLog;
use App\Model\User;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\DbConnection\Db;

class MinerAllBonusTask
{
    protected $signature = 'miner_all_bonus';

    protected $description = '定时任务计算矿机订单全网收益';

    /**
     * @Inject()
     * @var StdoutLoggerInterface
     */
    private $logger;

    public function handle()
    {
        try {
            $date = date('Ymd', strtotime('-1 day'));
            DB::beginTransaction();

            /* 查询今天已生成记录则不再往下执行 */
            $row = MinerUserOrderLog::query()->where([
                'coin' => 'fil',
                'type' => 6,
                'date' => $date,
            ])->first();
            if (empty($row)) {
                /* 查询是否已有等级结点 */
                $minerConfig = MinerConf::query()->first()->toArray();
                $conf = MinerLevelConf::query()->orderBy('community_performance', 'asc')->get()->toArray();
                if (!empty($conf)) {
                    var_dump(1111);
                    $baseCount = [];//个节点数量
                    $date = date('Ymd', strtotime('-1 day'));
                    /* 查询各节点数量 */
                    $levelCount = DB::select("select miner_level_id,count(id) as count from `user` where miner_level_id > 1 group by miner_level_id");
                    var_dump($levelCount);
                    if (!empty($levelCount)) {
                        echo '!empty($levelCount)';
                        $levelCount = array_column($levelCount, 'count', 'miner_level_id');
                        foreach ($conf as $key => $value) {
                            $baseCount[$value['id']] = 0;
                            if (!empty($levelCount[$value['id']])) {
                                $baseCount[$value['id']] = $levelCount[$value['id']];
                            }
                        }
                        $conf = array_column($conf, null, 'id');
                        $fil = MinerUserOrderLog::query()->where('date', $date)->where('type', 2)->sum('award_quantity');
                        foreach ($baseCount as $key => $value) {
                            /* 分段计算，防止数据太大 */
                            $count = $value;
                            $total = 1000;
                            if (!empty($count)) {
                                $page = ceil($count / $total); #计算总页面数
                                for ($i = 1; $i <= $page; $i++) {
                                    $user = User::query()->select('miner_level_id', 'id')
                                        ->where('miner_level_id', '=', $key)
                                        ->forPage($i, $total)
                                        ->get()
                                        ->toArray();
                                    $uid = array_column($user, 'id');
                                    /* 查询用户是否有认购过矿机，购买过才有资格分红 */
                                    $miner = MinerUserOrder::query()->select('user_id')->whereIn('user_id', $uid)->get()->toArray();
                                    if (!empty($miner)) {
                                        $minerUser = array_column($miner, 'user_id');
                                        foreach ($user as $k => $v) {
                                            if (in_array($v['id'], $minerUser)) {
                                                if ($fil > 0) {
                                                    // 团队收益 * 团队加权
                                                    /* 如果为1节点,则1+2节点总数，如果为2节点，则2+3节点总数 */
                                                    //一级节点份额
                                                    $oneNode = $baseCount[2] + $baseCount[3] + $baseCount[4];
                                                    $one = 0;
                                                    if ($oneNode != 0) {
                                                        $one = bcmul($fil, $conf[2]['whole_network_computing_power'], 8);
                                                        $one = bcdiv($one, $oneNode, 8);
                                                    }
                                                    //二级节点份额
                                                    $twoNode = $baseCount[3] + $baseCount[4];
                                                    $two = 0;
                                                    if ($twoNode != 0) {
                                                        $two = bcmul($fil, $conf[3]['whole_network_computing_power'], 8);
                                                        $two = bcdiv($two, $twoNode, 8);
                                                        $two = bcadd($two, $one, 8);
                                                    }
                                                    //三级节点份额
                                                    $threeNode = $baseCount[4];
                                                    $three = 0;
                                                    if ($twoNode != 0) {
                                                        $three = bcmul($fil, $conf[4]['whole_network_computing_power'], 8);
                                                        $three = bcdiv($three, $threeNode, 8);
                                                        $three = bcadd($three, $two, 8);
                                                    }
                                                    $number = 0;
                                                    switch ($v['miner_level_id']) {
                                                        case 2:
                                                            $number = $one;
                                                            break;
                                                        case 3:
                                                            $number = $two;
                                                            break;
                                                        case 4:
                                                            $number = $three;
                                                            break;
                                                    }
                                                    if ($number > 0) {
                                                        //$number = bcmul($number,'0.75',8);
                                                        $number = bcmul($number, $minerConfig['static_bobi'], 8);
                                                        $awardOrder = [
                                                            'user_id' => $v['id'],
                                                            'order_num' => (new MinerLogic())->generateOrderNumber($v['id']),
                                                            'power' => $number,
                                                            'award_first' => $minerConfig['hashrate_award_first'],
                                                            'award_remain' => $minerConfig['hashrate_award_remain'],
                                                            'award_day' => $minerConfig['hashrate_award_day'],//有效期天数
                                                            'award_remain_day' => 0,
                                                            'date' => $date,
                                                            'award_date' => date('Ymd'),
                                                            'created_at' => time(),
                                                            'updated_at' => time(),
                                                            'type' => 6,
                                                        ];
                                                        // 批量新增矿机收益订单
                                                        MinerUserAwardOrder::insert($awardOrder);
                                                        /*WalletLogic::saveUserSymbolAsset($v['id'], 'fil', 'miner_all_bonus', $number, $freeze = 0,  0, '挖矿全网加权奖励');
                                                        $orderLog = [
                                                            'user_id'           => $v['id'],
                                                            'order_num'         => MinerLogic::generateOrderNumber($v['id']),
                                                            'coin'              => 'fil',
                                                            'type'              => 6,
                                                            'award_quantity'    => $number,
                                                            'date'              => date('Ymd'),
                                                            'created_at'        => time()
                                                        ];
                                                        // 记录流水
                                                        MinerUserOrderLog::insert($orderLog);*/
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                var_dump("已生成当天数据，请勿重复执行");
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