<?php
namespace App\Process;

use App\Foundation\Common\RedisService;
use App\Task\SubMonthMinRankTask;
use Hyperf\Process\AbstractProcess;

class SubMonthMinRankCacheProcess extends AbstractProcess
{

    public function handle(): void
    {
        $redis = RedisService::getInstance('sub_user');
        while(true)
        {
            $isF5 = $redis->lPop('userTotalMonthF5');
            if (!$isF5)
            {
                sleep(5);
            }
            else
            {
                (new SubMonthMinRankTask())->execute();
                sleep(1);
            }
        }
    }


}
