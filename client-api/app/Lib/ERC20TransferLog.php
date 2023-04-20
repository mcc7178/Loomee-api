<?php


namespace App\Lib;

use App\Model\Auth\Member;
use App\Model\BlockLog;
use App\Model\ConfActivation;
use App\Model\Erc20ActivationAddress;
use App\Model\Erc20Address;
use App\Model\Product\Coin;
use App\Model\Product\Order;
use App\Model\Product\Product;
use App\Model\Product\ProductDynamic;
use App\Model\RechargeLog;
use Hyperf\DbConnection\Db;

class ERC20TransferLog
{
//    public $url = "https://mainnet.infura.io/v3/1a5167a478f748ea9e675bcb1e35fb25";
//    public $url = "https://mainnet.infura.io/v3/6951a8511b284c6db90af184e7287aa3";
    public $url = "https://rinkeby.infura.io/v3/1a5167a478f748ea9e675bcb1e35fb25";

    public $symbol = [
        'usdt' => [
            'contract' => '0xdac17f958d2ee523a2206206994597c13d831ec7',
            'decimals' => 6
        ],
        'eth' => [
            'decimals' => 18
        ],
    ];

    public $contra = [
        'ntf' => '0xa181A27Eb64e9B43871605B3e94483eFD1e39161',
        'transfer' => '0x54a7f9c5f5e218a7ff95a2c3e5ce792e55059076',
    ];

    /**
     * Notes://Todo 扫20次
     * User: Deycecep
     * DateTime: 2022/5/13 11:36
     * @param $hash
     * @return bool
     */
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
        $res = $common->requestApi($this->url, $params);
        if(isset($res['result']) && $res['result']['status']!='0x0') return true;
        return false;

    }

    public function getEthTransactionsLog($fromBlockDec)
    {
        $common = new Common();
        $params = [
            '0x'.$common->dec2hex($fromBlockDec),
            true
        ];
        $logs = $common->jsonRpc($this->url, 'eth_getBlockByNumber', $params);
//        var_dump('eth : '. $fromBlockDec .'交易数据量：'.count($logs));

        foreach ($logs['transactions'] as $log)
        {
            $hash = $log['hash'];
            $to =  $log['to'];

            if (!$log['value'])
            {
                continue;
            }
//            $value = $common->hex2dec($log['value'], 'getEthTransactionsLog'.__LINE__.'--'.json_encode($log['value']));

            // 判断是不是我们的合约
            $contra = [];
            foreach ($this->contra as $v){
                $contra[] =  strtolower($v);
            }

            if (!in_array(strtolower($to), $contra)){
                continue;
            }

            if(strtolower($to) == strtolower($this->contra['ntf'])){//NFT
                $symbol = 'NFT';
                $from =  '0x'.substr($log['input'], 34 + 64 * 7, 40);
                $orderId =  hexdec(substr($log['input'], 1610, 64));
                $op = hexdec(substr($log['input'], 1546, 64));
                $to =  $log['from'];
                $order = Order::getOneById($orderId);
                $product = Product::getOneByWhere(['id' => $order->product_id]);
            }
            if(strtolower($to) == strtolower($this->contra['transfer'])){//转移
                $op = 0;
                $orderId = 0;
                $symbol = 'TRANSFER';
                $from =  '0x'.substr($log['input'], 34, 40);
                $to =  '0x'.substr($log['input'], 34 + 64, 40);
                $tokenId =  hexdec(substr($log['input'], 34 + 64 + 40, 64));
                $product = Product::getOneByWhere(['tokenID' => $tokenId,'contract_url' => $this->contra['transfer']]);
            }

            $userInfo = Member::where('address',$to)->first();

            $receive = [
                'block_number' => $common->hex2dec($logs['number'], 'getBnbTransactionsLog'.__LINE__),
                'hash'=> $hash,
                'created_at'=> date('Y-m-d H:i:s'),
                'symbol' => $symbol,
                'from'=> $from,
                'to'=> $to,
                'result'=> json_encode($log),
                'status'=> 0,
                'input'=> $log['input'],
                'type' => $op,
                'user_id' => $userInfo->id,
                'product_id' => $product->id,
                'order_id' => $orderId,
                'scan_time' => date('Y-m-d H:i:s', hexdec($logs['timestamp']))
            ];

            BlockLog::query()->insert($receive);
//            if(!$this->checkHash($hash)){
//                continue;
//            }
//            if(strtolower($to) == strtolower($this->contra['ntf'])){//NFT
//                // 交易数据
////                var_dump('log'. json_encode($log));
//                $from =  '0x'.substr($log['input'], 34 + 64 * 7, 40);
//                $orderId =  hexdec(substr($log['input'], 1610, 64));
//                $op = hexdec(substr($log['input'], 1546, 64));
//                $to =  $log['from'];
//                $order = Order::getOneById($orderId);
//                $product = Product::getOneByWhere(['id' => $order->product_id]);
//                if($op == '1'){//购买
////                    var_dump('交易');
//                    if(empty($order->hash))
//                        $this->orderCallback($product,$from,$to,$hash,$orderId);
//                }elseif($op == '6'){//下架
//                    if($product->status == 1)
//                    $this->cancelProduct($product,$from,$to,$hash);
//                }
//            }
//            if(strtolower($to) == strtolower($this->contra['transfer'])){//转移
//                // 交易数据
////                var_dump('log'. json_encode($log));
//                $from =  '0x'.substr($log['input'], 34, 40);
//                $to =  '0x'.substr($log['input'], 34 + 64, 40);
//                $tokenId =  hexdec(substr($log['input'], 34 + 64 + 40, 64));
//                $product = Product::getOneByWhere(['tokenID' => $tokenId,'contract_url' => $this->contra['transfer']]);
//                if($product)
//                  $this->transferProduct($product,$from,$to,$hash);
//            }
        }
    }


    public  function  orderCallback($product,$from,$to,$hash,$orderId,$scanTime){

        $coinId = $product->coin_id;
        $coin = Coin::getOneByWhere(['id' => $coinId]);
        $userInfo = Member::where('address',$to)->first();
        Db::beginTransaction();
        try {
            $product->status = 0;
            $product->sales += $product->price;
            $product->sales_nums += 1;
            $product->chain_id = $coin->chain_id;
            $product->owner = $userInfo->address;
            $product->owner_id = $userInfo->id;
            $product->sold_time =  date('Y-m-d H:i:s');

            //产品动态
            $dynamicId = ProductDynamic::query()->insertGetId([
                'product_id' => $product->id,
                'event' => 4,
                'comefrom' => $from,
                'reach' => $to,
                'coin_id' => $coinId,
                'price' => $product->price,
                'coin_token' => $product->coin_token,
                'hash' => $hash,
                'created_at' => $scanTime,
                'updated_at' => $scanTime,
            ]);

            $orderStatus = Order::where('id', $orderId)->update(['status' => 1,'hash' => $hash]);
            if (!$product->save() || !$dynamicId || !$orderStatus) {
                Db::rollBack();
            }
            Db::commit();
        } catch (\Throwable $ex) {
            Db::rollBack();
        }
    }

    public  function  cancelProduct($product,$from,$to,$hash,$scanTime){
        $product->status = 0;
        $product->save();
        ProductDynamic::query()->insertGetId([
            'product_id' => $product->id,
            'event' => 6,
            'comefrom' => $from,
            'reach' => $to,
            'coin_id' => $product->coin_id,
            'price' => $product->price,
            'coin_token' => $product->coin_token,
            'hash' => $hash,
            'created_at' => $scanTime,
            'updated_at' => $scanTime,
        ]);

    }

    public  function  transferProduct($product,$from,$to,$hash,$scanTime){
        $userInfo = Member::where('address',$to)->first();
        $product->owner = $to;
        if($userInfo)
            $product->owner_id = $userInfo->id;
        $product->save();
        ProductDynamic::query()->insertGetId([
            'product_id' => $product->id,
            'event' => 5,
            'comefrom' => $from,
            'reach' => $to,
            'coin_id' => $product->coin_id,
            'price' => $product->price,
            'coin_token' => $product->coin_token,
            'hash' => $hash,
            'created_at' => $scanTime,
            'updated_at' => $scanTime,
        ]);

    }

    public function getErc20TransactionsLog($platform, $symbol)
    {
        $addressesData = $this->getAddress($platform);
        $addresses = $addressesData['address'];
        $common = new Common();
        $addresses = array_map(function ($e) use ($common){
            return '0x'.$common->encodeAbiAddress($e);
        }, $addresses);

        $params = [
            "id" => time(),
            "jsonrpc" => "2.0",
            "method" => "eth_blockNumber",
        ];

        $currentHeightHex = $common->jsonRpc($this->url, 'post', $params);
        $toBlockDec = hexdec($common->subHex0x($currentHeightHex['result']));
        $fromBlockDec = $toBlockDec - 50;

        $params = [
            "id" => time(),
            "jsonrpc" => "2.0",
            "method" => "eth_getLogs",
            "params" => [
                [
                    'fromBlock' => $common->addHex0x(dechex($fromBlockDec)),
                    'toBlock' => $common->addHex0x(dechex($toBlockDec)),
                    'address' => $this->symbol[$symbol]['contract'],
                    'topics'=>[
                        '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef',
                        null,
                        $addresses
                    ]
                ]
            ],
        ];


        $logs = $common->jsonRpc($this->url, 'post', $params);
        $receive = [];
        $platformAddressData = $addressesData['platformData'];

        foreach ($logs['result'] as $log){
            $quantityDec = $common->hex2dec($log['data'], 'getErc20TransactionsLog'.__LINE__);
            if (1 !== bccomp($quantityDec, '0', 0)) {
                continue;
            }

            $from = $common->decodeAbiAddress($log['topics'][1]);
            $to   = $common->decodeAbiAddress($log['topics'][2]);

            $value = $common->getValue($quantityDec,  $this->symbol[$symbol]['decimals']);

            $hash = $log['transactionHash'];
            $blockHash = $log['blockHash'];
            $blockNumber = hexdec($log['blockNumber']);
            $receive[] = [
                'from'=> $from,
                'to'=> $to,
                'value'=> $value,
                'hash'=> $hash,
                'blockHash'=> $blockHash,
                'blockNumber'=>$blockNumber
            ];

            $res = RechargeLog::query()
                ->where('hash', $hash)->first();
            if ($res)
                continue;
            $platformInfo =  'external';
            if (isset($platformAddressData[$to]))
            {
                $platformInfo = $platformAddressData[$to];
            }

            RechargeLog::query()->insert([
                'from' => $from,
                'to' => $to,
                'hash' => $hash,
                'value' => $value,
                'created_at' => time(),
                'symbol' => $symbol,
                'platform' => $platformInfo,
                'block_number' => $blockNumber,
                'block_hash' => $blockHash,
                'block_time' => date("Y-m-d H:i:s"),
                'chain' => 'erc20',
            ]);
        }
    }

    public function getAddress($platform)
    {
        if($platform === 'all')
            $platform = false;

        $platformAddress = Erc20Address::query()
            ->when($platform, function ($query) use ($platform) {
                return $query->where('platform', $platform);
            })->select('address','platform')->get();

        if ($platformAddress->isEmpty())
        {
            return [];
        }
        $addresses = $platformAddressData = [];
        foreach ($platformAddress as $addressData){
            $addresses[] = $addressData->address;
            $platformAddressData[$addressData->address] = $addressData->platform;
        }
        return [
            'address' => $addresses,
            'platformData' => $platformAddressData
        ];
    }
}
