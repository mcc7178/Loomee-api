<?php

use Hyperf\Crontab\Crontab;

return [
    //是否开启定时任务
    'enable' => true,
     // 通过配置文件定义的定时任务
    'crontab' => [
        // Callback类型定时任务（默认）
        (new Crontab())->setName('Foo')->setRule('*/2 * * * *')->setCallback([App\Task\Laboratory\CollectionTradeTotalTask::class, 'execute'])->setMemo('定时汇总合集每日交易额'),

    ],
];