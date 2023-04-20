<?php
declare(strict_types=1);

namespace App\Process;


use Hyperf\Process\AbstractProcess;
use App\Foundation\Facades\Log;
use App\Foundation\Common\RedisService;
use App\Logic\SubscribeBonus;

class StaticBonusProcess extends AbstractProcess
{

    protected $signature = 'StaticBonusProcess';
    protected $description = '处理队列';

    protected $queue_key = 'staticBonus:task_queue';
    protected $queue_success = 'staticBonus:queue_success';
    protected $queue_error = 'staticBonus:queue_error';
    protected $queue_faile_queue = 'staticBonus:queue_faile_queue';

    protected $queue_button = 'staticBonus:queue_button';
    protected $queue_count = 'staticBonus:task_queue_count';

    protected $queue_end = 'staticBonus:queue_end';

    public function handle(): void
    {
        $redis = RedisService::getInstance('sub_user');

        while(true)
        {
            $len = $redis->Llen("{$this->queue_key}");
            if($len > 0)
            {
                $queue_error = $this->queue_error.':'.date('Ymd');
                $qe = $redis->get("{$queue_error}");
                if(!$qe)    $redis->set("{$queue_error}",0);

                $butoon = $redis->get("{$this->queue_button}");
                if(!$butoon)    
                {
                    // $now_success = 'staticBonus:'.date("H:i:s").'task_success';
                    // $now_success = 'staticBonus:'.date("H:i:s").'task_success';

                    $this->runMain();
                    sleep(3);
                }
                else
                {
                    $queue_count = $redis->get("{$this->queue_count}");
                    if($queue_count > 0)
                    {
                        $queue_success = $this->queue_success.':'.date('Ymd');
                        $queue_success_nums = $redis->get("{$queue_success}");

                        if(($queue_count - ($queue_success_nums + $len)) < 400)
                        {
                            $redis->set("{$this->queue_button}",'open',1);
                        }
                    }
                    
                    sleep(1);
                }
            }
            else
            {       
                $qe = $redis->get("{$this->queue_end}");
                if(!$qe)   $redis->set("{$this->queue_end}",time(),300);
                sleep(5);
            }
        }
    }

    public function runMain()
    {
        $queue_success = $this->queue_success.':'.date('Ymd');
        
        $redis = RedisService::getInstance('sub_user');
        $qs = $redis->get("{$queue_success}");
        if(!$qs)    $redis->set("{$queue_success}",0);

        for($j = 0;$j<10;$j++)
        {
            co(function () use ($queue_success) {
                for($i=0;$i<20;$i++)
                {
                    $redis = RedisService::getInstance('sub_user');
                    $data = $redis->lpop("{$this->queue_key}");
                    if(!$data)  break;
                    SubscribeBonus::new_staticBonus(json_decode($data,true),$queue_success);
                }
            });
        }

        $redis = RedisService::getInstance('sub_user');
        $redis->set("{$this->queue_button}",'open',30);
    }






}
