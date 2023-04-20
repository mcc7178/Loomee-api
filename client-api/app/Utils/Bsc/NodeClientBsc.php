<?php

namespace App\Utils\Bsc;

use Exception;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;
use Web3\Web3;

class NodeClientBsc extends Web3
{
    function __construct($url)
    {
        $provider = new HttpProvider(
            new HttpRequestManager($url, 300) //timeout
        );
        parent::__construct($provider);
    }

    static function create($network)
    {
        if ($network === 'mainNet') return self::mainNet();
        if ($network === 'testNet') return self::testNet();
        throw new Exception('unsupported network');
    }

    static function testNet()
    {
        return new self('https://data-seed-prebsc-1-s1.binance.org:8545');
    }

    static function mainNet()
    {
        return new self('https://bsc-dataseed1.binance.org');
    }
}
