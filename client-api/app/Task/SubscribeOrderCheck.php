<?php
namespace App\Task;

use App\Model\SubscribeConf;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Crontab\Annotation\Crontab;
use Hyperf\Di\Annotation\Inject;
use Hyperf\DbConnection\Db;
use App\Logic\SubscribeBonus;
use App\Foundation\Facades\Log;
use App\Foundation\Common\RedisService;
use App\Model\SubscribeOrder;

// 认购订单的定时检测
class SubscribeOrderCheck
{
    protected $description  = '认购订单的定时检测';
    protected $check_key    = 'subscribe_check:history_sum';
    protected $res_key      = 'subscribe_check:resinfo';

    public function handle()
    {
        try{
            $this->staticBonus();
        }
        catch(\Exception $e)
        {
            Log::debugLog()->debug($e->getMessage()." || ".$e->getFile().' || '.$e->getLine());
        }
    }


    public function staticBonus()
    {
        $redis = RedisService::getInstance('sub_user');
        $list = Db::table('subscribe_order')
            ->where('not_release_quantity', '>', 0)
            ->where('created_at', '<', strtotime(date("Y-m-d")))
            ->where('status', 'processing')
            // ->where('release_at', '<>', strtotime(date("Y-m-d")))
            // ->limit(1000)
            ->get()
            ->toArray();

        if(count($list) == 0)   return null;

        $pop = array_chunk($list,100,true);

        $nums = 0;

        $redis->hset("{$this->res_key}", (string)date('Y-m-d H:i:s'), "开始执行检测");

        foreach($pop as $k=>$v)
        {
            $nums++;
            co(function () use ($v) {
                $redis = RedisService::getInstance('sub_user');
                foreach($v as $ka=>$va)
                {
                    $update = [];
                    $sum_u = Db::table('subscribe_order_release_log')
                    ->where('order_id', $va->id)
                    ->sum('release_quantity');
        
                    $sum_lqd = Db::table('subscribe_order_release_log')
                    ->where('order_id', $va->id)
                    ->sum('quantity');

                    $updateData = [];
                    if(bccomp((string)$sum_u, (string)($va->quantity * 3), 10) != -1)
                    {         
                        $updateData['release_quantity'] = $va->quantity * 3;
                        $updateData['release_bonus_quantity'] = $sum_lqd;
                        $updateData['not_release_quantity'] = 0;
                        $updateData['status'] = 'success';
                        SubscribeOrder::query()->where('id',$va->id)->update($updateData);
                        $update = [
                            'old' => $va,
                            'update_info' => $updateData
                        ];
                        $redis->hset("{$this->res_key}", (string)date('Y-m-d H:i:s'), json_encode($update));
                    }

                    $redis->hset("{$this->check_key}", "{$va->id}", json_encode([
                        'sum_u'=>$sum_u,
                        'sum_lqd'=>$sum_lqd,
                    ]));
                }
            });
            if($nums%5 == 0) sleep(2);
        }
    }
}
