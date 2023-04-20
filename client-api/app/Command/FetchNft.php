<?php

declare(strict_types=1);

namespace App\Command;

use App\Foundation\Facades\Log;
use App\Service\NftReptileService;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputArgument;

/**
 * @Command
 */
class FetchNft extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct('fetch:nft');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('抓取链上NFT数据');
    }

    public function handle()
    {
        Log::codeDebug()->info("抓取NFT数据start");
        go(function () {
            (new NftReptileService())->handle();
        });
        Log::codeDebug()->info("抓取NFT数据end");
    }
}
