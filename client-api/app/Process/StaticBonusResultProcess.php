<?php
declare(strict_types=1);

namespace App\Process;


use Hyperf\Process\AbstractProcess;
use App\Foundation\Facades\Log;
use App\Foundation\Common\RedisService;
use App\Logic\SubscribeBonus;

// 整理收益队列任务结果
class StaticBonusResultProcess extends AbstractProcess
{

    protected $signature = 'StaticBonusResultProcess';
    protected $description = '处理队列';

    protected $queue_key = 'staticBonus:task_queue';
    protected $queue_success = 'staticBonus:queue_success';
    protected $queue_error = 'staticBonus:queue_error';

    protected $queue_count = 'staticBonus:task_queue_count';

    protected $queue_result = 'staticBonus:queue_result';

    protected $queue_end = 'staticBonus:queue_end';

    public function handle(): void
    {
        $redis = RedisService::getInstance('sub_user');

        while(true)
        {
            $queue_count = $redis->get("{$this->queue_count}");
            if($queue_count > 0)
            {
                $recode = false;
                $key_success = $this->queue_success.':'.date('Ymd');
                $key_error = $this->queue_error.':'.date('Ymd');

                $queue_success = $redis->get("{$key_success}");
                $queue_error = $redis->get("{$key_error}");

                if($queue_count == ($queue_success + $queue_error))
                {
                    $recode = true;
                }
                else
                {
                    $len = $redis->Llen("{$this->queue_key}");
                    if($len == 0)
                    {
                        $queue_end = $redis->get("{$this->queue_end}");
                        if($queue_end)
                        {
                            if((time() - $queue_end) > 60)
                            {
                                $recode = true;
                            }
                        }
                    }
                }

                if($recode)
                {
                    $arr = [
                        'title' => 'the queue end',
                        'queue_count' => $queue_count,
                        'queue_success' => $queue_success,
                        'queue_error' => $queue_error,
                        'time' => date('Y-m-d H:i:s'),
                    ];
    
                    $key_result = $this->queue_result.':'.date('Ymd');
    
                    $redis->hset("{$key_result}", date("Y-m-d H:i:s"), json_encode($arr));
    
                    $redis->set("{$key_success}",0,10);
                    $redis->set("{$key_error}",0,10);
                    $redis->set("{$this->queue_count}",0,10);

                    $redis->set("{$this->queue_end}",time(),1);
                }
                
                sleep(2);
            }
            else
            {       
                sleep(5);
            }
        }
    }

  





}
