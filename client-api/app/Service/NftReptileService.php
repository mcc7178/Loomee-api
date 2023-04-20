<?php

namespace App\Service;

use App\Constants\RedisKey;
use App\Foundation\Facades\Log;
use App\Lib\Common;
use App\Model\Auth\Member;
use App\Model\BlockLog;
use App\Model\Collection\Collection;
use App\Model\Product\Product;
use App\Model\Product\ProductAttribute;
use App\Model\Product\ProductDynamic;
use App\Task\NftPicHandleTask;
use App\Utils\ContractConfig;
use App\Utils\Redis;
use Hyperf\DbConnection\Db;
use Swoole\Runtime;


class NftReptileService
{
    protected $contra = ContractConfig::CONTRACT2;

    protected $toAddress = [
        '0x392075b2' => 'level_up',//单用户升级
        '0x523ae15d' => 'set_parent',//设置上级
        '0xfa578db6' => 'buy',  //USDT买单
        '0x89ca0994' => 'sell',  //DCC卖单
        '0xad9a8ad9' => 'miner',  //挖矿
        '0xcf78bf1c' => 'earnings',  //领取收益，判断input参数决定是复投还是挂卖
        '0xb84eb76a' => 'cancelSell',
        '0xb84eb76a1' => 'canceBuy',
        '0x1072cbea' => 'deductionUSTD',
    ];

    public $url = 'https://bsc-dataseed.binance.org/';
    public $abi = '[{"inputs":[{"internalType":"string","name":"name_","type":"string"},{"internalType":"string","name":"symbol_","type":"string"}],"stateMutability":"nonpayable","type":"constructor"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"owner","type":"address"},{"indexed":true,"internalType":"address","name":"approved","type":"address"},{"indexed":true,"internalType":"uint256","name":"tokenId","type":"uint256"}],"name":"Approval","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"owner","type":"address"},{"indexed":true,"internalType":"address","name":"operator","type":"address"},{"indexed":false,"internalType":"bool","name":"approved","type":"bool"}],"name":"ApprovalForAll","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"from","type":"address"},{"indexed":true,"internalType":"address","name":"to","type":"address"},{"indexed":true,"internalType":"uint256","name":"tokenId","type":"uint256"}],"name":"Transfer","type":"event"},{"inputs":[{"internalType":"address","name":"to","type":"address"},{"internalType":"uint256","name":"tokenId","type":"uint256"}],"name":"approve","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"owner","type":"address"}],"name":"balanceOf","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"tokenId","type":"uint256"}],"name":"getApproved","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"address","name":"owner","type":"address"},{"internalType":"address","name":"operator","type":"address"}],"name":"isApprovedForAll","outputs":[{"internalType":"bool","name":"","type":"bool"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"name","outputs":[{"internalType":"string","name":"","type":"string"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"tokenId","type":"uint256"}],"name":"ownerOf","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"address","name":"from","type":"address"},{"internalType":"address","name":"to","type":"address"},{"internalType":"uint256","name":"tokenId","type":"uint256"}],"name":"safeTransferFrom","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"from","type":"address"},{"internalType":"address","name":"to","type":"address"},{"internalType":"uint256","name":"tokenId","type":"uint256"},{"internalType":"bytes","name":"_data","type":"bytes"}],"name":"safeTransferFrom","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"operator","type":"address"},{"internalType":"bool","name":"approved","type":"bool"}],"name":"setApprovalForAll","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"bytes4","name":"interfaceId","type":"bytes4"}],"name":"supportsInterface","outputs":[{"internalType":"bool","name":"","type":"bool"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"symbol","outputs":[{"internalType":"string","name":"","type":"string"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"tokenId","type":"uint256"}],"name":"tokenURI","outputs":[{"internalType":"string","name":"","type":"string"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"address","name":"from","type":"address"},{"internalType":"address","name":"to","type":"address"},{"internalType":"uint256","name":"tokenId","type":"uint256"}],"name":"transferFrom","outputs":[],"stateMutability":"nonpayable","type":"function"}]';
    public $method = 'tokenURI';
    public $owner_method = 'ownerOf';

//    public $url = 'https://data-seed-prebsc-1-s1.binance.org:8545/';

    public function handle($data = '')
    {
        Log::codeDebug()->info("nft抓取" . $data);
        if (strtolower(env('APP_ENV')) == 'dev') {
            $url = \App\Constants\Common::TEST_ETH_API;              //测试
        } else {
            $url = \App\Constants\Common::PRODUCT_ETH_API;              //正式
        }
        if ($data) {
            $data = json_decode($data, true);
            $contractConfig = [$data['contract']];
        } else {
            $contractConfig = ContractConfig::TEST_CONTRACT_LIST;
            /*if (env('APP_ENV') == 'dev') {
                $url = 'https://api.etherscan.io/api?';      //正式
//            $url = 'https://api-rinkeby.etherscan.io/api?';              //测试
                $contractConfig = ContractConfig::TEST_CONTRACT_LIST;
            } else {
                $url = 'https://api-rinkeby.etherscan.io/api?';              //测试
                $contractConfig = ContractConfig::CONTRACT_LIST;
            }*/
        }
        Log::codeDebug()->info("nft抓取,contractConfig:" . json_encode($contractConfig));

        ini_set('memory_limit', -1);
        Runtime::enableCoroutine();
        foreach ($contractConfig as $contract) {
            $params = "module=account&action=tokennfttx&contractaddress={$contract}&address=0x0000000000000000000000000000000000000000&page=1&offset=10000&startblock=0&endblock=latest&sort=asc&apikey=JJKF2MX14C4HWP9C8BN5CS964CK5Z5IC6D";
            $result = curl_get("$url?$params");
            Log::codeDebug()->info("message:{$result['message']},status:{$result['status']}");
            if ($result && !empty($result['status']) && $result['status'] == 1) {
                Log::codeDebug()->info(json_encode($result['result'][0]));
                Log::codeDebug()->info(json_encode($result['message']));
                if ($result['message'] == 'OK') {
                    foreach ($result['result'] as $key => $item) {
                        go(function () use ($key, $item, $contract) {
                            $this->handleNftData($item, $contract);
                        });
                        sleep(1);
                    }
                }
            } else {
                Log::codeDebug()->info("获取数据失败 ,url:$url?$params,message:{$result['message']}");
                break;
            }
            //Log::codeDebug()->info("抓取第1页数据成功,总条数:$total");
        }
    }

    /*{
        "blockNumber": "10866447",
        "timeStamp": "1655447976",
        "hash": "0x4324bc59955232975e7d8bd70f02609110e0b657e17e728585bdc456e1093e32",
        "nonce": "188",
        "blockHash": "0x4a600d80621922f6868e161ae6464e2ce90defd784b45af6b901b1a8c9051ff4",
        "from": "0x0000000000000000000000000000000000000000",
        "contractAddress": "0x48d9ced65c279e67d232e9a1bf7de240e0847676",
        "to": "0x7a6d60243d32ed3e5123cc834a38eba8ea4080b9",
        "tokenID": "322",
        "tokenName": "Solar.Runner",
        "tokenSymbol": "SLR",
        "tokenDecimal": "0",
        "transactionIndex": "6",
        "gas": "10381168",
        "gasPrice": "2500000021",
        "gasUsed": "10379112",
        "cumulativeGasUsed": "10711004",
        "input": "deprecated",
        "confirmations": "108525"
    }*/
    public function handleNftData($item, $contract)
    {
        $redis = Redis::getInstance();
        $date = date('Y-m-d H:i:s');
        $tokenID = $item['tokenID'];
        $tokenName = $item['tokenName'] ?? '';
        $collectionModel = Collection::where('contract', $contract)->first();
        if (!$collectionModel) {
            $collection_id = Collection::insertGetId([
                'name' => $item['tokenName'],
                'chain_id' => $data['chain_id'] ?? 1,
                'cate_id' => $data['cate_id'] ?? 0,
                'owner' => $data['owner'] ?? '',
                'contract' => $contract,
                'desctiption' => '',
                'created_at' => $date,
                'updated_at' => $date,
            ]);
            //Log::codeDebug()->info("新增合集 id:$collection_id");
        } else {
            $collection_id = $collectionModel->id;
        }
        //Log::codeDebug()->info("collection_id:" . $collection_id);

        $type = "更新";
        $productModel = Product::where('name', "$tokenName #$tokenID")->where('contract_url', $contract)->first();
        if (!$productModel) {
            $type = "新增";
            $productModel = new Product();
        }

//        Log::codeDebug()->info("curl");
        $abiResult = (new UtilsService())->apiContractUrl($contract, $this->abi, $this->method, [$tokenID . '']);
//        Log::codeDebug()->info("abiresult:" . $abiResult);
        $picture = $url = '';
        $attrResult = [];
        if ($abiResult) {
            if (strpos($abiResult, 'ipfs://') !== false) {
                $url = "https://ipfs.io/ipfs/" . explode('/', explode('//', $abiResult)[1])[0] . "/$tokenID";
                if (!empty(pathinfo($abiResult)['extension']) && (pathinfo($abiResult)['extension'] == 'json')) {
                    $url = $url . '.json';
                }
            } else {
                $url = $abiResult;
            }
//            Log::codeDebug()->info("url:" . $url);
            $attrResult = curl_get($url);
            if (!$attrResult || (!empty($attrResult['message']) && stripos($attrResult['message'], 'error') !== false)) {

                //队列重试
                $redis->lPush(RedisKey::NFT_REPTILE_RETRY, json_encode(['tokenID' => $tokenID, 'contract' => $contract]));

                Log::codeDebug()->info("获取NFT详情数据失败:$url,contract:$contract,result:" . json_encode($attrResult));
                return true;
            }
            if (!empty($attrResult['image'])) {
                if (strpos($attrResult['image'], 'ipfs://') !== false) {
                    $exp = explode('//', $attrResult['image']);
                    $picture = 'https://ipfs.io/ipfs/' . $exp[1];
                } else {
                    $picture = $attrResult['image'];
                }
            }
        }
        $name = $attrResult && !empty($attrResult['name']) ? $attrResult['name'] : $tokenName . ' #' . $tokenID;
        $productModel = Product::where('name', $name)->where('contract_url', $contract)->first();
        if (!$productModel) {
            $type = "新增";
            $productModel = new Product();
        }
        $owner = (new UtilsService())->apiContractUrl($contract, $this->abi, $this->owner_method, [$tokenID . '']);
        $prdData = [
            'name' => $name,
            'symbol' => $item['tokenSymbol'] ?: '',
            'introduction' => $attrResult && !empty($attrResult['description']) ? $attrResult['description'] : '',
            'picture' => $picture,
            'animation_url' => $attrResult && !empty($attrResult['animation_url']) ? $attrResult['animation_url'] : '',
            'collection_id' => $collection_id,
            'contract_url' => $contract,
            'author' => $item['to'] ?? '',
            'owner' => $owner ?: ($data['owner'] ?? ''),
            'chain_id' => $data['chain_id'] ?? 1,
            'cate_id' => $data['cate_id'] ?? 0,
            'tokenID' => $item['tokenID'],
            'source_url' => $url,
            'coin_token' => $tokenID,
            'source' => json_encode($item),
            'created_at' => $date,
            'updated_at' => $date,
        ];
        if ($type == '新增') {
            $product_id = $productModel->insertGetId($prdData);
        } else {
            $productModel->save($prdData);
            $product_id = $productModel->id;
        }
        $time = date('Y-m-d H:i:s', $item['timeStamp'] ?? 0);
        if (!ProductDynamic::query()->where(['product_id' => $product_id, 'event' => 1])->exists()) {
            $dynamicData = [
                'product_id' => $product_id,
                'event' => 1,
                'hash' => $item['hash'] ?? '',
                'comefrom' => '',
                'reach' => $item['to'] ?? '',
                'coin_token' => $tokenID,
                'created_at' => $time,
                'updated_at' => $time,
            ];
            ProductDynamic::insert($dynamicData);
            //Log::codeDebug()->info("新增NFT动态数据" . json_encode($dynamicData));
        }
        if ($attrResult && !empty($attrResult['attributes'])) {
            $attrArr = array_column($attrResult['attributes'], 'trait_type');
            $element = Collection::where('id', $collection_id)->first();
            if (!$element->element || array_diff($attrArr, $element->element) != []) {
                $element->update(['element' => $attrArr]);
            }
            $attrData = [];
            ProductAttribute::query()->where('product_id', $product_id)->forceDelete();
            foreach ($attrResult['attributes'] as $attr) {
                $attrData[] = [
                    'product_id' => $product_id,
                    'title' => $attr['trait_type'],
                    'value' => $attr['value'],
                    'created_at' => $date,
                    'updated_at' => $date,
                ];
                $itemKey = RedisKey::COLLECTION_ATTRIBUTE . $collection_id;
                $redis->sAdd($itemKey, $attr['trait_type']);
                $redis->sAdd($itemKey . '_' . $attr['trait_type'], $attr['value']);
            }
            if ($attrData) {
                ProductAttribute::insert($attrData);
                //Log::codeDebug()->info("新增NFT属性数据,count:" . count($attrData));
            }
        }

        $picHandleTask = new NftPicHandleTask();
        $picHandleTask->handlePic($productModel->id, './pic/' . $productModel->picture);
        $picHandleTask->handleAnimation($productModel->id, './pic/' . $productModel->animation_url);
        Log::codeDebug()->info("$type NFT处理完成:$tokenName $tokenID,product_id:$product_id");
        return true;
//        $total += 1;
//        Log::codeDebug()->info("$type NFT数据：$name, produdct_id:$product_id,当前第1页,第{$total}条");
    }

    public function getBnbTransactionsLog($fromBlockDec)
    {
        try {
            $common = new Common();
            $params = [
                '0x' . $common->dec2hex($fromBlockDec),
                true
            ];
            $logs = $common->jsonRpc($this->url, 'eth_getBlockByNumber', $params);
            if (!$logs)
                return false;

            foreach ($logs['transactions'] as $log) {

                $hash = $log['hash'];

                if (!$log['value']) {
                    continue;
                }

                $input = $log['input'];
                if (!$input || $input == '0x')
                    continue;
                if (substr($input, -64) == '0x')
                    continue;

                $tos = substr($input, 0, 10);
                if (!isset($this->toAddress[$tos])) {
                    continue;
                }

                $to = $log['to'];
                if (!in_array(strtolower($to), $this->contra)) {
                    continue;
                }

                $r = BlockLog::query()->where('hash', $hash)->value('id');
                if ($r) {
                    continue;
                }

                $from = $log['from'];
                $symbol = strtoupper(array_search($to, $this->contra));

                $decValue = $common->hex2dec(substr($input, -64), 'getBnbTransactionsLog' . __LINE__);
                $decValue = $common->getValue($decValue, 18);

                if (in_array(strtolower($tos), ['0x523ae15d', '0x07d6b348']))
                    $decValue = 0;

                if (strtolower($from) == '0x094612c8ce09283c624a3a3e3c5a17bac5de352c')
                    $from = '0x' . substr($input, 34, 40);

                $user = Member::query()->where('address', strtolower($from))->first();
                if (!$user)
                    continue;

                $receive = [
                    'block_number' => $common->hex2dec($logs['number'], 'getBnbTransactionsLog' . __LINE__),
                    'hash' => $hash,
                    'created_at' => time(),
                    'updated_at' => time(),
                    'from' => $log['from'],
                    'to' => $to,
                    'result' => json_encode($log),
                    'status' => 0,
                    'symbol' => $symbol,
                    'quantity' => $decValue,
                    'input' => $input,
                    'type' => $this->toAddress[$tos],
                    'user_id' => $user->id
                ];

                BlockLog::query()->insert($receive);
            }
            return true;
        } catch (\Throwable $e) {
            Log::codeDebug()->info("BSCTransferLogTask" . date("Y-m-d H:i:s") . 'msg:' . $e->getMessage() . '; file: ' . $e->getFile() . '; line:' . $e->getLine());
            return false;
        }
    }

    /**
     * 刷新NFT数据
     * @param $id
     * @param $contract
     * @param $tokenID
     * @return bool
     */
    public function refresh($id, $contract, $tokenID)
    {
        if (!$tokenID) {
            $productModel = Product::query()->findOrFail($id)->toArray();
        } else {
            $productModel = Product::query()->where("tokenID", $tokenID)->where("contract_url", $contract)->get()->toArray();
        }
        $this->handleNftData(json_decode($productModel['source'], true), $contract);

        //刷新静态资源数据
        $newModel = Product::query()->find($id);
        $picHandleTask = new NftPicHandleTask();
        $picHandleTask->handlePic($id, './pic/' . $newModel->picture);
        $picHandleTask->handleAnimation($id, './pic/' . $newModel->animation_url);
        Log::codeDebug()->info("刷新成功,{$newModel->name} {$newModel->tokenID}");
        return true;
    }
}