<?php


namespace App\Lib\BSC;


use App\Lib\Common;

class BSCCheckWithdrawHash
{
    public function checkHash($hash)
    {
        $params = [
            "id" => time(),
            "jsonrpc" => "2.0",
            "method" => "eth_getTransactionReceipt",
            "params" => [
                $hash
            ],
        ];
        $common = new Common();
        $url = (new BSCTransferLog())->url;
        $res = $common->requestApi($url, $params);
        if(isset($res['result']) && $res['result']['status']!='0x0') return true;
        return false;

    }

}