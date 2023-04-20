<?php
declare(strict_types=1);


namespace App\Lib;

use App\Lib\Common;
use App\Lib\EncryptionKey;
use Hyperf\Utils\ApplicationContext;
use Web3p\EthereumUtil\Util;

class ETHNewBlockNumber
{
//    private $url = "https://mainnet.infura.io/v3/3e5a5d9950d1472497d8ca8cb0fb13a8";
    private $url = "https://rinkeby.infura.io/v3/1a5167a478f748ea9e675bcb1e35fb25";

    public function getNewBlock()
    {
        $common = new Common();
        $number = $common->jsonRpc($this->url, 'eth_blockNumber');
        if (!$number)
            return false;

        $number = $common->hex2dec($common->subHex0x($number), 'ETHNewBlockNumber'.__LINE__);
        $redis = ApplicationContext::getContainer()->get(\Hyperf\Redis\Redis::class);
        $redis->set('eth_blockNumber', $number);
    }
}