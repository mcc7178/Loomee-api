<?php
namespace App\Task;

use App\Lib\ETHNewBlockNumber;

use Yurun\Util\HttpRequest;
use Hyperf\Crontab\Annotation\Crontab;
use Hyperf\Di\Annotation\Inject;

class ETHNewBlockNumberTask
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

        try{
            (new ETHNewBlockNumber())->getNewBlock();
        }catch (\Exception $e){
            $this->logger->error('ETHNewBlockNumber'.$e->getMessage(),[$e->getMessage().'---'.$e->getCode()]);
        }

    }
}