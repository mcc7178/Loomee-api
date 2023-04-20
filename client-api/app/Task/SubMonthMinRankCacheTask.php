<?php
namespace App\Task;

use App\Logic\UserLogic;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Crontab\Annotation\Crontab;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Utils\ApplicationContext;
use App\Foundation\Common\RedisService;


class SubMonthMinRankCacheTask
{
    /**
     * @Inject()
     * @var \Hyperf\Contract\StdoutLoggerInterface
     */
    private $logger;

    public function execute()
    {
        // $redis = ApplicationContext::getContainer()->get(\Hyperf\Redis\Redis::class);
        $redis = RedisService::getInstance('sub_user');
        $isF5 = $redis->lPop('userTotalMonthF5');
        if (!$isF5)
        {
            return ;
        }
        (new SubMonthMinRankTask())->execute();

    }


}
