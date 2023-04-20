<?php


namespace App\Lib\BSC;

use App\Lib\Common;
use App\Log;
use App\Model\BlockLog;
use App\Model\User;
use App\Utils\ContractConfig;
use Hyperf\Utils\ApplicationContext;

class BSCTransferLog
{

    protected $contra = ContractConfig::CONTRACT;

    protected $toAddress = [
        '0x392075b2'    => 'level_up',//单用户升级
        '0x523ae15d'    => 'set_parent',//设置上级
        '0xfa578db6'    => 'buy',  //USDT买单
        '0x89ca0994'    => 'sell',  //DCC卖单
        '0xad9a8ad9'    => 'miner',  //挖矿
        '0xcf78bf1c'    => 'earnings',  //领取收益，判断input参数决定是复投还是挂卖
        '0xb84eb76a'    => 'cancelSell',
        '0xb84eb76a1'    => 'canceBuy',
        '0x1072cbea'    => 'deductionUSTD',
    ];


//    public $url = 'https://bsc-dataseed.binance.org/';
    public $url = 'https://data-seed-prebsc-1-s1.binance.org:8545/';

    public function getBnbTransactionsLog($fromBlockDec)
    {
        try{

            $common = new Common();
            $params = [
                '0x'.$common->dec2hex($fromBlockDec),
                true
            ];
            $logs = $common->jsonRpc($this->url, 'eth_getBlockByNumber', $params);
            if (!$logs)
                return false;

            foreach ($logs['transactions'] as $log){

                $hash = $log['hash'];

                if ($hash == '0xab960f2539e1b7405691ff4cd4d0986e806ddf4f8194b309cf602f3f08f2fcbb')
                {
                    var_dump('来了。；。1111111111111');
                }

                if (!$log['value']) {
                    continue;
                }

                if ($hash == '0xab960f2539e1b7405691ff4cd4d0986e806ddf4f8194b309cf602f3f08f2fcbb')
                {
                    var_dump('来了。；。22222222222222222');
                }


                $input = $log['input'];
                if (!$input || $input == '0x')
                    continue;
                if (substr($input, -64) == '0x')
                    continue;

                $tos = substr($input, 0, 10);
                if(!isset($this->toAddress[$tos])){
                    if ($hash == '0xab960f2539e1b7405691ff4cd4d0986e806ddf4f8194b309cf602f3f08f2fcbb')
                    {
                        var_dump('来了。；。33333333333');
                    }

                    continue;
                }

                if ($hash == '0xab960f2539e1b7405691ff4cd4d0986e806ddf4f8194b309cf602f3f08f2fcbb')
                {
                    var_dump('来了。；。4444444444444444');
                }


                $to =  $log['to'];
                if (!in_array( strtolower($to),  $this->contra)) {
                    if ($hash == '0xab960f2539e1b7405691ff4cd4d0986e806ddf4f8194b309cf602f3f08f2fcbb')
                    {
                        var_dump('来了。；。5555555555555555555555');
                    }

                    continue;
                }

                $r = BlockLog::query()->where('hash', $hash)->value('id');
                if ($r) {
                    if ($hash == '0xab960f2539e1b7405691ff4cd4d0986e806ddf4f8194b309cf602f3f08f2fcbb')
                    {
                        var_dump('来了。；。6666666666666666666');
                    }

                    continue;
                }

                $from = $log['from'];
                $symbol = strtoupper(array_search($to,$this->contra));

                $decValue = $common->hex2dec(substr($input, -64), 'getBnbTransactionsLog'.__LINE__);
                $decValue = $common->getValue($decValue, 18);

                if (in_array( strtolower($tos), ['0x523ae15d','0x07d6b348'])) {
                    $decValue = 0;
                }
                $user = User::query()->where('address', strtolower($from))->first();

                if (!$user)
                {
                    if ($hash == '0x1b19d0399554813622de000aea9743133f85c494d72b59522d537d9dfa87fef5')
                    {
                        var_dump('来了。；。77777777777777777');
                        var_dump($log);
                    }

                    continue;
                }

                $receive = [
                    'block_number' => $common->hex2dec($logs['number'], 'getBnbTransactionsLog'.__LINE__),
                    'hash'=> $hash,
                    'created_at'=> time(),
                    'updated_at'=> time(),
                    'from'=> $log['from'],
                    'to'=> $to,
                    'result'=> json_encode($log),
                    'status'=> 0,
                    'symbol'=> $symbol,
                    'quantity'=> $decValue,
                    'input'=> $input,
                    'type' => $this->toAddress[$tos],
                    'user_id' => $user->id
                ];

                BlockLog::query()->insert($receive);
            }
            return true;
        }catch (\Exception $e){
            Log::get()->error("BSCTransferLogTask".date("Y-m-d H:i:s"). 'msg:'.$e->getMessage().'; file: '.$e->getFile().'; line:'.$e->getLine());
            return  false;
        }
    }
    public function getBnbTransactionsLogTwo($fromBlockDec)
    {
        $common = new Common();
        $params = [
            '0x'.$common->dec2hex($fromBlockDec),
            true
        ];
        $logs = $common->jsonRpc($this->url, 'eth_getBlockByNumber', $params);
        if (!$logs)
        {
            var_dump('高度没数据：'.$fromBlockDec);
//
            return false;
        }

//        $address = User::query()->select('address','id')->get()->keyBy('address');
        foreach ($logs['transactions'] as $log){

            $hash = $log['hash'];
            if (!$log['value'])
                continue;

            $input = $log['input'];
            if (!$input || $input == '0x')
                continue;

            if (substr($input, -64) == '0x')
                continue;

            $tos = substr($input, 0, 10);

            // 不是这个合约
            $from = $log['from'];
            $to =  $log['to'];

            if (!in_array( strtolower($to),  $this->contra))
                continue;

            if(!isset($this->toAddress[$tos]))
                continue;

            if (BlockLog::query()->where('hash', $hash)->value('id')) {
                continue;
            }

            $symbol = strtoupper(array_search($to,$this->contra));

            $decValue = $common->hex2dec(substr($input, -64), 'getBnbTransactionsLog'.__LINE__);
            $decValue = $common->getValue($decValue, 18);
            $to = '0x'.substr($input, 34, 40);

            $user = User::query()->where('address', strtolower($from))->first();
            if (!$user)
                continue;

            if (in_array( strtolower($tos), ['0x523ae15d','0x07d6b348'])) {
                $decValue = 0;
            }
            //var_dump($log['hash']);
            $receive = [
                'block_number' => $common->hex2dec($logs['number'], 'getBnbTransactionsLog'.__LINE__),
                'hash'=> $hash,
                'created_at'=> time(),
                'updated_at'=> time(),
                'from'=> $log['from'],
                'to'=> $to,
                'result'=> json_encode($log),
                'status'=> 0,
                'symbol'=> $symbol,
                'quantity'=> $decValue,
                'input'=> $input,
                'type' => $this->toAddress[$tos],
                'user_id' =>$user->id
            ];
//            var_dump('hash 入库：'.$hash);

            BlockLog::query()->insert($receive);
        }
        return true;

    }

    protected function getBscTransactionsLog($fromBlockDec,$toBlockDec,$erc20ContractAddress)
    {
        $common = new Common();
        $params = [
            [
                'fromBlock'=> $common->addHex0x( $common->dec2hex($fromBlockDec) ),
                'toBlock'=>$common->addHex0x( $common->dec2hex($toBlockDec)),
                'address'=> $erc20ContractAddress,
            ]
        ];
        $logs = $common->jsonRpc($this->url, 'eth_getBlockByNumber', $params);
        $hashData = BlockLog::query()->select('id', 'hash')->get()->keyBy('hash');
        foreach ($logs as $log){
            $hash = $log['transactionHash'];
            if (isset($exists[$hash])){
                continue;
            }
            if (in_array($hash, $hashData))
            {
                continue;
            }
            $receive = $this->getTransactionByHash($hash);
            $to = $receive['to'];
        }
        if (!empty($receiveArray))
        {
            // TODO 入库
            return $receiveArray;
        }
    }

    protected function getTransactionByHash($hash)
    {
        $params = [
            $hash
        ];

        $common = new Common();
        $log = $common->jsonRpc($this->url, 'eth_getTransactionByHash', $params);
        $blockNumber = $common->hex2dec($log['blockNumber'], 'eth_getTransactionByHash'.__LINE__);
        $save = [
            'block_number'     => $blockNumber,
            'hash'             => $hash,
            'from'             => $log['from'],
            'to'               => $log['to'],
            'input'            => $log['input'],
            'result'           => json_encode($log),
        ];
        return $save;
    }

}

