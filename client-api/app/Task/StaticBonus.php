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

class StaticBonus
{
    protected $description = '定时任务计算每日静态收益';
    protected $cache_key = 'staticBonus:task_button';
    protected $queue_key = 'staticBonus:task_queue';
    protected $queue_count = 'staticBonus:task_queue_count';

    protected $queue_result = 'staticBonus:queue_result';

    public function handle()
    {
        $redis = RedisService::getInstance('sub_user');
        $staticBonus_sign = $redis->get("{$this->cache_key}");
        if($staticBonus_sign)   return null;

        $staticBonus_sign = $redis->set("{$this->cache_key}",time(),300);

        Log::debugLog()->debug("StaticBonus start:".date("Y-m-d H:i:s"));
        try{
            $this->staticBonus();
        }
        catch(\Exception $e)
        {
            Log::debugLog()->debug($e->getMessage()." || ".$e->getFile().' || '.$e->getLine());
        }
        Log::debugLog()->debug("StaticBonus end:".date("Y-m-d H:i:s"));
    }


    public function staticBonus()
    {
        $list = Db::table('subscribe_order')
            ->where('not_release_quantity', '>', 0)
            ->where('created_at', '<', strtotime(date("Y-m-d")))
            ->where('status', 'processing')
            ->where('release_at', '<>', strtotime(date("Y-m-d")))
            // ->limit(3000)
            ->get()
            ->toArray();

        if(count($list) == 0)   return null;

        $redis = RedisService::getInstance('sub_user');
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

}
