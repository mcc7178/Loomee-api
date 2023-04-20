<?php

use App\Task\BSCTransferLogTask;
use Hyperf\Crontab\Crontab;

return [
    //是否开启定时任务
    'enable' => true,
    // 通过配置文件定义的定时任务
    'crontab' => [
        // Callback类型定时任务（默认）
        (new Crontab())->setName('Foo')->setRule('*/5 * * * * *')
            ->setCallback([App\Task\BinancePriceTask::class, 'handle'])->setMemo('这是一个示例的定时任务'),

        (new Crontab())->setName('BSCTransferLogTask')->setRule('*/2 * * * * *')
            ->setCallback([BSCTransferLogTask::class, 'execute'])->setMemo('bsc 扫块'),

        (new Crontab())->setName('ETHNewBlockNumberTask')->setRule('*/5 * * * * *')
            ->setCallback([App\Task\ETHNewBlockNumberTask::class, 'execute'])->setMemo('块高度'),

        (new Crontab())->setName('ETHTransferLogTask')->setRule('*/5 * * * * *')
            ->setCallback([App\Task\ETHTransferLogTask::class, 'execute'])->setMemo('扫块'),

        (new Crontab())->setName('BlockLogCheckTask')->setRule('*/2 * * * * *')
            ->setCallback([\App\Task\BlockLogCheckTask::class, 'execute'])->setMemo('检测hash'),

        (new Crontab())->setName('NftReptileRetryTask')->setRule('* */1 * * * *')
            ->setCallback([\App\Task\NftReptileRetryTask::class, 'handle'])->setMemo('NFT爬虫重试'),

        (new Crontab())->setName('NftPicHandleTask')->setRule('*/30 * * * * *')
            ->setCallback([\App\Task\NftPicHandleTask::class, 'handle'])->setMemo('NFT图片转存1'),

        (new Crontab())->setName('NftFetchTask')->setRule('*/5 * * * * *')
            ->setCallback([\App\Task\NftFetchTask::class, 'handle'])->setMemo('个人NFT数据抓取'),
    ],
];