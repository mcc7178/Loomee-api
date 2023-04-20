<?php
namespace App\Task;

use App\Lib\ERC20\ERC20TransferLog;
use Hyperf\Utils\ApplicationContext;
use Yurun\Util\HttpRequest;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Crontab\Annotation\Crontab;


class ETHTransferLogTask
{
    /**
     * @Inject
     * @var HttpRequest
     */
    protected $HttpRequest;

    /**
     * @Inject()
     * @var \Hyperf\Contract\StdoutLoggerInterface
     */
    private $logger;
    public function execute()
    {
        //var_dump('eth扫描: 还在进行中 time: '. date("Y-m-d H:i:s"));
        try{
            $redis = ApplicationContext::getContainer()->get(\Hyperf\Redis\Redis::class);
            if( $redis->exists('is_eth_transferLog') )
            {
               // var_dump('eth扫描: 还在进行中 time: '. date("Y-m-d H:i:s"));
                return true;
            }

            $fromBlockDec = $redis->get('eth_transferLogNumber');
//            var_dump('开始eth扫描:'. $fromBlockDec. '; time: '. date("Y-m-d H:i:s"));
            $newBlock = $redis->get('eth_blockNumber');
            if($fromBlockDec > ( $newBlock-1 ))
                return true;

            $redis->setex('is_eth_transferLog', 60,$fromBlockDec);

            (new \App\Lib\ERC20TransferLog())->getEthTransactionsLog($fromBlockDec);

            $redis->incr('eth_transferLogNumber');
         //   var_dump('扫描eth结束:'. $fromBlockDec. '; time: '. date("Y-m-d H:i:s"));
            $redis->del('is_eth_transferLog');

        }catch (\Exception $e){
            $redis->hSet('getEthTransactionsLog', date("Y-m-d H:i:s"), 'msg:'.$e->getMessage().'; file: '.$e->getFile().'; line:'.$e->getLine());
            $this->logger->error('createdAddress'.$e->getMessage(),[$e->getMessage().'---'.$e->getCode()]);
        }

    }
}
