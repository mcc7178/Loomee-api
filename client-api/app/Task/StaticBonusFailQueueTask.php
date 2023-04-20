<?php
declare(strict_types=1);

namespace App\Task;


use Hyperf\Process\AbstractProcess;
use App\Foundation\Facades\Log;
use App\Foundation\Common\RedisService;
use App\Logic\SubscribeBonus;
use Hyperf\DbConnection\Db;
use App\Model\SubscribeConf;

// 失败的队列任务处理
class StaticBonusFailQueueTask
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

    public function handle(string $date = ''): void
    {
        $redis = RedisService::getInstance('sub_user');
        $key_result = $this->queue_result.':'.date('Ymd');
    
        $this->staticBonus($date);
    }

    public function staticBonus($date)
    {
        if($date == '') $date = date('Y-m-d');
        try{
            $release_ids = Db::table('subscribe_order_release_log')
                ->where('date_time', $date)
                ->where('type', 'self')
                ->groupBy('order_id')
                ->pluck('order_id')->toArray();

            $order_ids = Db::table('subscribe_order')
                ->where('created_at', '<', strtotime($date))
                ->where('status', 'processing')
                ->pluck('id')->toArray();

            $fail_list = array_values(array_diff($order_ids,$release_ids));
            Log::debugLog()->debug('fail_list:'.json_encode($fail_list));
            Log::debugLog()->debug('fail_list nums:'.count($fail_list));
            return null;
            $list = Db::table('subscribe_order')
                ->where('created_at', '<', strtotime($date))
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
