<?php


namespace App\Lib\BSC;


use App\Lib\Common;

class BSCRpc
{
    protected $url = 'https://bsc-dataseed.binance.org/';

    public function getEthBlockNumber()
    {
        $data = (new Common())->jsonRpc($this->url, 'eth_blockNumber');

        return hexdec((new Common())->subHex0x($data['result']));
    }
}