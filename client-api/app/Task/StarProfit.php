<?php
namespace App\Task;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Crontab\Annotation\Crontab;
use Hyperf\Di\Annotation\Inject;

class StarProfit
{
    protected $description = '定时任务计算每日英雄日榜';
    public function handle()
    {
        (new \App\Logic\SubscribeBonus)->starProfit();
    }
}