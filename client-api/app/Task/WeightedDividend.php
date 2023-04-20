<?php
namespace App\Task;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Crontab\Annotation\Crontab;
use Hyperf\Di\Annotation\Inject;
use App\Logic\SubscribeBonus;
use App\Exception\DbException;
use Hyperf\DbConnection\Db;

class WeightedDividend{
    protected $description = '定时任务计算节点加权收益';

    public function handle()
    {
        (new SubscribeBonus)->weightedDividend();
    }
}