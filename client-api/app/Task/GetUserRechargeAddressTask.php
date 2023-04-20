<?php

namespace App\Task;

use App\Services\CoinService;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Config\Config;

class GetUserRechargeAddressTask
{
    /**
     * @Inject()
     * @var StdoutLoggerInterface
     */
    private $logger;
    
    /**
     * @Inject
     * @var ConfigInterface
     */
    private $config;
    
    protected $signature = 'getRechargeAddress {chain}';
    
    protected $description = '获取充值地址';
    
    public function handle()
    {
        try {
            $chain = 'fil';
            (new CoinService())->getCoinAddressByChainId($chain);
        } catch (\Throwable $ex) {
            DB::rollback();
            $msg = $ex->getMessage() . $ex->getFile() . '--' . $ex->getLine();
            $this->logger->error($msg);
            var_dump($msg);
        }
    }
}