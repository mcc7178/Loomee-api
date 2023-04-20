<?php


namespace App\Lib\BSC;


use App\Lib\BSCLIB\Credential;
use App\Lib\BSCLIB\Kit;
use App\Lib\BSCLIB\NodeClient;
use App\Lib\Common;
use App\Lib\EncryptionKey;
use App\Model\Conf;
use App\Model\ConfCollet;
use App\Model\RechargeLog;
use App\Model\Withdraw;
use kornrunner\Keccak;
use xtype\Ethereum\Client as EthereumClient;
use xtype\Ethereum\Utils;

class BSCSendTransfer
{
    protected $symbol = [
        'filt' => [
            'contract' => '',
            'decimals' => 18
        ],
        'bnb' => [
            'decimals' => 18
        ],
    ];

    public function sendBsc($uniquely_identifies, $address, $value, $symbol)
    {
        $common = new  Common();
        $decimals = 18;
        if (!$common->isAddress($address)){
            throw new \Exception("wrong address! {$address}", 422);
        }


        $contract = $this->symbol[strtoupper($symbol)]; // 币种合约地址

        $fromC = Conf::query()->where('chain', 'bep20')->first();
        $privateKey = (new EncryptionKey())->authCode($fromC->gpk, substr( $fromC->slat, 1, EncryptionKey::P_STR_LEN).'_private');
        $from = $fromC->address; // 项目方地址

        $trans = [
            "from" => $from,
            "to" => $contract,
            "value" => '0x0',
        ];

        $hash = Keccak::hash("transfer(address,uint256)",256);
        $hash_sub = mb_substr($hash,0,8,'utf-8');

        $value = $common->unit2wei($value,  $decimals);
        // 16进制
        $value = $common->dec2hex($value);

        $data = '0x'.$hash_sub;
        $data .= str_pad(substr($address, 2), 64, '0', STR_PAD_LEFT);
        $data .= str_pad($value, 64, '0', STR_PAD_LEFT);

        $url = 'https://bsc-dataseed3.defibit.io/'; // bsc RPC地址

        $client = new EthereumClient([
            'base_uri' => $url,
            'timeout' => 30,
        ]);

        $client->addPrivateKeys([$privateKey]);

        $trans['data'] = $data;
        $trans['gas'] = dechex(hexdec($client->eth_estimateGas($trans)));
        $trans['gasPrice'] = $client->eth_gasPrice();
        $trans['nonce'] = $client->eth_getTransactionCount($from, 'pending');

        $txid = $client->sendTransaction($trans);
        ($client->eth_getTransactionReceipt($txid));

        // 入库
        Withdraw::query()->create([
            'uniquely_identifies' => $uniquely_identifies,
            'symbol' => $symbol,
            'value' => $value,
            'to' => $address,
            'chain' => 'BEP20',
            'hash' => $txid,
            'from' => $fromC->address,
            'is_check' => 0
        ]);

        return $txid;
    }

    public function sendBnbT($uniquely_identifies, $address, $value, $symbol)
    {
        $_value = $value;
        $common = new  Common();
        $decimals = 18;
        if (!$common->isAddress($address)){
            throw new \Exception("wrong address! {$address}", 422);
        }

        $fromC = Conf::query()->where('chain', 'bep20')->first();
        $privateKey = (new EncryptionKey())->authCode($fromC->gpk, substr( $fromC->slat, 1, EncryptionKey::P_STR_LEN).'_private');
        $from = $fromC->address; // 项目方地址

        $trans = [
            "from" => $from,
            "to" => $address,
            "value" => Utils::ethToWei($value, true),
        ];

//        $hash = Keccak::hash("transfer(address,uint256)",256);
//        $hash_sub = mb_substr($hash,0,8,'utf-8');

//        $value = $common->unit2wei($value,  $decimals);
        // 16进制
//        $value = $common->dec2hex($value);

//        $data = '0x'.$hash_sub;
//        $data .= str_pad(substr($address, 2), 64, '0', STR_PAD_LEFT);
//        $data .= str_pad($value, 64, '0', STR_PAD_LEFT);

        $url = 'https://bsc-dataseed3.defibit.io/'; // bsc RPC地址

        $client = new EthereumClient([
            'base_uri' => $url,
            'timeout' => 30,
        ]);

        $client->addPrivateKeys([$privateKey]);

//        $trans['data'] = $data;
        $trans['data'] = '0x';
        $trans['gas'] = dechex(hexdec($client->eth_estimateGas($trans)));
        $trans['gasPrice'] = $client->eth_gasPrice();
        $trans['nonce'] = $client->eth_getTransactionCount($from, 'pending');

        $txid = $client->sendTransaction($trans);
        ($client->eth_getTransactionReceipt($txid));

        // 入库
        Withdraw::query()->create([
            'uniquely_identifies' => $uniquely_identifies,
            'symbol' => 'BNB',
            'value' => $_value,
            'to' => $address,
            'chain' => 'BEP20',
            'hash' => $txid,
            'from' => $fromC->address,
            'is_check' => 0
        ]);

        return $txid;
    }

    public function sendBnb($uniquely_identifies, $address, $value, $symbol)
    {
        $common = new  Common();
        if (!$common->isAddress($address)){
            throw new \Exception("wrong address! {$address}", 422);
        }

        var_dump('$address:'.$address);
        $fromC = Conf::query()->where('chain', 'bep20')->first();
        $privateKey = (new EncryptionKey())->authCode($fromC->gpk, substr( $fromC->slat, 1, EncryptionKey::P_STR_LEN).'_private');

        var_dump('$privateKey:'.$privateKey);
        $kit = new Kit(
            NodeClient::create('mainNet'),
            Credential::fromKey($privateKey)
        );
        $sender = $kit->getSender();
        var_dump('$sender:'.$sender);
        // echo 'from  => ' . $sender . PHP_EOL;

        $balance = $kit->balanceOf($sender);
        $balance = bcdiv($balance, pow(10, 18), 5);
        var_dump('$balance:'.$balance);
        if($balance < $value)
            throw new \Exception("BNB 余额不足", 422);

        $value = $this->hex(bcmul($value, pow(10, 18)));

        $txid = $kit->transfer(
            $address,
            $value
        );

        var_dump('$txid: '.$txid);

        // 入库
        $a = Withdraw::query()->create([
            'uniquely_identifies' => $uniquely_identifies,
            'symbol' => 'BNB',
            'value' => $value,
            'to' => $address,
            'chain' => 'BEP20',
            'hash' => $txid,
            'from' => $fromC->address,
            'is_check' => 0
        ]);

        var_dump('=============='.$a);

        $kit->waitForConfirmation($txid);
        return $txid;
    }


    public function hex($str, $prefix=true){
        $bn = gmp_init($str);
        $ret = gmp_strval($bn, 16);
        return $prefix ? '0x' . $ret : $ret;
    }

    public function sendTransferBnb($to, $privateKey, $from, $value)
    {
        $_value = $value;
        $fromC = ConfCollet::query()->where('chain', 'bep20')->first();

        $trans = [
            "from" => $from,
            "to" => $to,
            "value" => Utils::ethToWei($value, true),
        ];


        $url = 'https://bsc-dataseed3.defibit.io/'; // bsc RPC地址

        $client = new EthereumClient([
            'base_uri' => $url,
            'timeout' => 30,
        ]);

        $client->addPrivateKeys([$privateKey]);

//        $trans['data'] = $data;
        $trans['data'] = '0x';
        $trans['gas'] = dechex(hexdec($client->eth_estimateGas($trans)));
        $trans['gasPrice'] = $client->eth_gasPrice();
        $trans['nonce'] = $client->eth_getTransactionCount($from, 'pending');

        $txid = $client->sendTransaction($trans);
        ($client->eth_getTransactionReceipt($txid));


        // 入库
        Withdraw::query()->create([
            'uniquely_identifies' => $uniquely_identifies,
            'symbol' => 'BNB',
            'value' => $_value,
            'to' => $address,
            'chain' => 'BEP20',
            'hash' => $txid,
            'from' => $fromC->address,
            'is_check' => 0
        ]);

        return $txid;
    }

}
