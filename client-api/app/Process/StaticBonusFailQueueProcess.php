<?php
declare(strict_types=1);

namespace App\Process;


use Hyperf\Process\AbstractProcess;
use App\Foundation\Facades\Log;
use App\Foundation\Common\RedisService;
use App\Logic\SubscribeBonus;
use Hyperf\DbConnection\Db;
use App\Model\SubscribeConf;

// 失败的队列任务处理
class StaticBonusFailQueueProcess extends AbstractProcess
{

    protected $signature = 'StaticBonusFailQueueProcess';
    protected $description = '失败的队列任务处理';

    protected $queue_key = 'staticBonus:task_queue';
    protected $queue_success = 'staticBonus:queue_success';
    protected $queue_error = 'staticBonus:queue_error';
    protected $queue_count = 'staticBonus:task_queue_count';
    protected $queue_result = 'staticBonus:queue_result';
    protected $queue_end = 'staticBonus:queue_end';
    protected $queue_rerun = 'staticBonus:queue_rerun';

    public function handle(): void
    {
        $redis = RedisService::getInstance('sub_user');
        
        while(true)
        {
            $key_result = $this->queue_result.':'.date('Ymd');
            $list = $redis->hGetAll("{$key_result}");
            if($list)
            {
                if(strstr(json_encode($list), 'the queue end'))
                {
                    $h = date('H');
                    $i = date('i');
                    if($h == 1 &&  $i >= 30 &&  $i%20 == 0 )
                    {
                        $time = 0;
                        foreach($list as $k=>$v)
                        {
                            if(strtotime($k) > $time)   $time = strtotime($k);
                        }

                        if((time() - $time) > 300)
                        {
                            $this->staticBonus();
                            sleep(61);
                        }
                    }
                }
                else
                {
                    sleep(10);
                }
            }
            else
            {
                sleep(10);
            }
        }
    }

    public function staticBonus()
    {
        try{
            $release_ids = Db::table('subscribe_order_release_log')
                ->where('date_time', date('Y-m-d'))
                ->where('type', 'self')
                ->groupBy('order_id')
                ->pluck('order_id')->toArray();

            $order_ids = Db::table('subscribe_order')
                ->where('created_at', '<', strtotime(date("Y-m-d")))
                ->where('status', 'processing')
                ->pluck('id')->toArray();

            $fail_list = array_values(array_diff($order_ids,$release_ids));

            $list = Db::table('subscribe_order')
                ->where('created_at', '<', strtotime(date("Y-m-d")))
                ->where('status', 'processing')
                ->whereIn('id',$fail_list)
                ->get()
                ->toArray();

            $redis = RedisService::getInstance('sub_user');

            $queue_rerun_key = $this->queue_rerun.':'.date('m-d H:i');
            $redis->set("{$queue_rerun_key}",json_encode($fail_list));

            $redis->set("{$this->queue_count}",count($list),1800);

            $result_arr = [
                'title' => 'the queue start',
                'queue_count' => count($list),
                'time' => date('Y-m-d H:i:s'),
            ];

            $key_result = $this->queue_result.':'.date('Ymd');
            $redis->hset("{$key_result}", date("Y-m-d H:i:s"), json_encode($result_arr));

            $multiple = SubscribeConf::query()->value('multiple') ?: 3;
            foreach ($list as &$item) {
                $item->quantity = bcmul((string)$item->quantity, (string)$multiple, 8);
            }
            $pop = array_chunk($list,100,true);

            foreach($pop as $k=>$v)
            {
                co(function () use ($v, $multiple) {
                    $redis = RedisService::getInstance('sub_user');
                    foreach($v as $ka=>$va)
                    {
                        $redis->lpush("{$this->queue_key}",json_encode($va));
                    }
                });
            }

        }
        catch(\Exception $e)
        {
            Log::debugLog()->debug($e->getMessage()." || ".$e->getFile().' || '.$e->getLine());
        }

    }





}
