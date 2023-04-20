<?php

namespace App\Task;

use App\Logic\WalletLogic;
use App\Model\MinerConf;
use App\Model\MinerUserOrder;
use App\Model\MinerUserOrderLog;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;

class MinerOrder
{
    protected $signature = 'miner_order';

    protected $description = '定时任务计算矿机认购订单奖励';

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
            $coin = 'usdt';
            $date = date('Ymd');
            $time = time();
            $count = MinerUserOrder::select('user_id')
                ->where('award_date', '=', $date)
                ->whereRaw('award_num_use < award_num')
                ->count();
            $total = 2000;
            if (!empty($count)) {
                $page = ceil($count / $total); #计算总页面数
                /* 分段计算，防止数据太大 */
                $conf = MinerConf::query()->first()->toArray();
                for ($i = 1; $i <= $page; $i++) {
                    $dateUpdateId = [];//记录要修改日期的id，处理完统一+30天
                    $award = [];//统一处理要user_id奖励的金额
                    $orderLog = [];
                    $orderId = [];//依据order_id计算释放usdt
                    $order = MinerUserOrder::query()->select('id', 'order_num', 'user_id', 'quantity')
                        ->where('award_date', '=', $date)
                        ->whereRaw('award_num_use < award_num')
                        ->forPage($i, $total)
                        ->get()->toArray();
                    $quantity = 0;
                    foreach ($order as $key => $value) {
                        if (empty($award[$value['user_id']])) {
                            $award[$value['user_id']] = 0;
                        }
                        $quantity = bcmul($value['quantity'], $conf['award_proportion'], 8);
                        $orderLog[] = [
                            'user_id' => $value['user_id'],
                            'order_num' => $value['order_num'],
                            'coin' => $coin,
                            'award_quantity' => $quantity,
                            'date' => $date,
                            'created_at' => $time
                        ];
                        $dateUpdateId[] = $value['id'];
                        /* 用户总计新增数量 */
                        $award[$value['user_id']] += $quantity;
                        $orderId[$value['id']] = $quantity;
                    }
                    /* 批量修改下次奖励日期+30，奖励次数记录+1 */
                    MinerUserOrder::query()->whereIn('id', $dateUpdateId)
                        ->update([
                            'award_date' => date('Ymd', strtotime('+30 days', $time)),
                            'award_num_use' => DB::raw('award_num_use + 1'),
                        ]);
                    /* 批量新增流水日志 */
                    MinerUserOrderLog::insert($orderLog);

                    /* 新增收益到用户账户 */
                    foreach ($award as $key => $value) {
                        (new WalletLogic())->saveUserSymbolAsset($key, $coin, 'miner_static_bonus', $value, $freeze = 0, 0, '矿机静态挖矿收益');
                        /*UserAssetRecharges::query()
                            ->updateOrCreate(
                                ['userid'=>$key,'coin'=>'usdt'],
                                ['userid'=>$key,'coin'=>'usdt','quantity'=>DB::raw('quantity +'.$value)]
                            );*/
                    }
                    foreach ($orderId as $k => $v) {
                        MinerUserOrder::query()
                            ->updateOrCreate(
                                ['id' => $k],
                                ['release_usdt_quantity' => DB::raw('release_usdt_quantity +' . $v)]
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
}