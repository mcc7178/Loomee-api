<?php
declare(strict_types=1);


namespace App\Lib\BSC;

use App\Lib\Common;
use App\Lib\EncryptionKey;
use BitWasp\Bitcoin\Crypto\Random\Random;
use BitWasp\Bitcoin\Key\Factory\HierarchicalKeyFactory;
use BitWasp\Bitcoin\Mnemonic\Bip39\Bip39Mnemonic;
use BitWasp\Bitcoin\Mnemonic\Bip39\Bip39SeedGenerator;
use BitWasp\Bitcoin\Mnemonic\MnemonicFactory;
use Hyperf\Utils\ApplicationContext;
use Web3p\EthereumUtil\Util;
use App\Model\Erc20Address as Erc20AddressModel;

class BSCNewBlockNumber
{
    public function getNewBlock()
    {
        $common = new Common();
        $url = (new BSCTransferLog())->url;
        $number = $common->jsonRpc($url, 'eth_blockNumber');
        if (!$number)
            return false;

        $number = $common->hex2dec($common->subHex0x($number), 'BSCNewBlockNumber'.__LINE__);
        $redis = ApplicationContext::getContainer()->get(\Hyperf\Redis\Redis::class);
        $redis->set('bsc_blockNumber', $number);
        $redis->set('bsc_blockNumbers', $number);
    }
}
