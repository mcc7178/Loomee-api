<?php

namespace App\Task;

use App\Service\NftReptileService;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Utils\ApplicationContext;

class BSCTransferLogTask
{
    /**
     * @Inject()
     * @var StdoutLoggerInterface
     */
    private $logger;

    public function execute()
    {
        try {
            $redis = ApplicationContext::getContainer()->get(\Hyperf\Redis\Redis::class);
            if ($redis->exists('is_bsc_transferLog')) {
                return true;
            }

            $newBlock = $redis->get('bsc_blockNumber');

            if (!$redis->exists('bsc_transferLogNumber')) {
                $fromBlockDec = $newBlock - 2;
                $redis->set('bsc_transferLogNumber', $fromBlockDec);
            } else
                $fromBlockDec = $redis->get('bsc_transferLogNumber');

            if ($fromBlockDec > ($newBlock - 2)) {
                return true;
            }

            $redis->setex('is_bsc_transferLog', 10, $fromBlockDec);

            $res = (new NftReptileService())->getBnbTransactionsLog($fromBlockDec);
            if ($res)
                $redis->incr('bsc_transferLogNumber', 1);
            $redis->del('is_bsc_transferLog');

        } catch (\Throwable $e) {
            $this->logger->error("BSCTransferLogTask" . date("Y-m-d H:i:s") . 'msg:' . $e->getMessage() . '; file: ' . $e->getFile() . '; line:' . $e->getLine());
        }
    }
}