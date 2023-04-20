<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constants\Common;
use App\Model\Collection\Collection;
use App\Model\Collection\CollectionCategory;
use App\Model\Collection\CollectionTradeTotal;
use App\Model\Product\Chain;
use App\Model\Product\Coin;
use App\Model\Product\MemberFollow;
use App\Model\Product\Order;
use App\Model\Product\OrderSign;
use App\Model\Product\Product;
use App\Model\Product\ProductDynamic;
use App\Model\Product\ProductSign;
use App\Model\Setting;
use App\Service\Auth\UserService;
use App\Service\NftReptileService;
use App\Service\UtilsService;
use App\Utils\Redis;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class NFTController extends AbstractController
{
    //排序方式
    private $orderBy = [
        1 => ['orderField' => 'product.shelves_time', 'orderType' => 'desc'],   //最近上架
        2 => ['orderField' => 'product.created_at', 'orderType' => 'desc'],     //最新创建
        3 => ['orderField' => 'product.sold_time', 'orderType' => 'desc'],      //最近卖出
        4 => ['orderField' => 'product.price', 'orderType' => 'asc'],           //价格升序
        5 => ['orderField' => 'product.price', 'orderType' => 'desc'],          //价格降序
    ];

    /**
     * Notes: 筛选列表
     * User: Deycecep
     * DateTime: 2022/4/18 16:35
     * @return ResponseInterface
     */
    public function getOptions()
    {
        $collectionName = $this->request->input('collection_name') ?? '';
        $collectionList = Collection::getList($collectionName);
        $collectionCategory = CollectionCategory::getList();
        $sellStatus = Setting::getOneByWhere(['sign_table' => 'product', 'sign_field' => 'sell_type']);
        $productStatus = Setting::getOneByWhere(['sign_table' => 'product', 'sign_field' => 'status']);
        $dynamicStatus = Setting::getOneByWhere(['sign_table' => 'product_dynamics', 'sign_field' => 'event']);
        $chainList = Chain::getList();
        $coinList = Coin::getList(['id', 'chain_id', 'name', 'image']);
        return $this->success([
            'collectionList' => $collectionList,
            'collectionCategory' => $collectionCategory,
            'sellStatus' => $sellStatus,
            'productStatus' => $productStatus,
            'chainList' => $chainList,
            'coinList' => $coinList,
            'dynamicStatus' => $dynamicStatus,
        ]);
    }

    /**
     * 产品列表
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    public function list(RequestInterface $request)
    {
        $input = $request->all();
        $list = (new Product())->getList($input);
        return $this->success($list);
    }

    /**
     * 产品详情
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    public function detail(RequestInterface $request)
    {
        $id = (int)$request->input('id', 0);
        $token = $request->header('Authorization');
        if ($token) {
            $userInfo = UserService::getInstance()->getUserInfoByToken();
            $user_id = $userInfo->id;
        }
        $detail = Product::detail($id, $user_id ?? 0);
        return $this->success($detail);
    }

    /**
     * 个人中心-产品详情
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    public function my_product_detail(RequestInterface $request)
    {
        $id = (int)$request->input('id', 0);
        $address = $this->request->input('address');
        if (!$address)
            $this->throwExp(400, __('validation.not_empty', ['title' => 'address']));
        $userInfo = UserService::getInstance()->getUserInfoByAddress($address);
        if (!$userInfo)
            $this->throwExp(400, __('validation.illegal', ['title' => 'address']));

        $detail = Product::detail($id, $userInfo->id);
        return $this->success($detail);
    }


    /**
     * Notes:我的NFT
     * User: Deycecep
     * DateTime: 2022/5/7 13:45
     * @param RequestInterface $request
     * @return ResponseInterface
     * @throws \App\Exception\Handler\BusinessException
     */
    public function myProduct(RequestInterface $request)
    {
        $params = $request->all();
        if (!isset($params['address']) || !$params['address'])
            $this->throwExp(400, __('validation.not_empty', ['title' => 'address']));

        $params['own'] = 1;
        $list = (new Product())->getList($params);
        return $this->success($list);
    }

    /**
     * Notes: NFT上下架
     * User: Deycecep
     * DateTime: 2022/4/19 19:44
     * @return ResponseInterface
     * @throws \App\Exception\Handler\BusinessException
     */
    public function updateProductStatus()
    {
        $status = $this->request->input('status') ?? '0';

        $id = $this->request->input('id') ?? '0';
        if (empty($id))
            $this->throwExp(400, __('validation.not_empty', ['title' => 'ID']));

        $userInfo = UserService::getInstance()->getUserInfoByToken();
        $product = Product::getOneById($id, $userInfo->id);
        if (empty($product))
            $this->throwExp(400, __('validation.illegal', ['title' => 'product']));

        if ($product->status == 0 && $status == 0)
            return $this->successByMessage(__('validation.success_to_edit'));

        $price = $this->request->input('price') ?? '0';
        if ($price <= 0)
            $this->throwExp(400, __('validation.illegal', ['title' => 'price']));

        $coinId = $this->request->input('coin_id') ?? '0';
        $coin = Coin::getOneByWhere(['id' => $coinId]);
        if (empty($coin))
            $this->throwExp(400, __('validation.illegal', ['title' => 'coin']));

        if ($status == 1) {
            $orderHash = $this->request->input('order_hash', '');
            $orderSign = $this->request->input('order_sign', '');
            $orderStructure = $this->request->input('order_structure', '');
            if (empty($orderHash) || empty($orderStructure) || empty($orderSign)) {
                $this->throwExp(400, __('validation.illegal', ['title' => 'product on the shelf']));
            }
        }
        $coinToken = $this->request->input('coin_token', '');

        $hash = $this->request->input('hash', '');
        if ($status == 0 && empty($hash))
            $this->throwExp(400, __('validation.not_empty', ['title' => 'hash']));
        Db::beginTransaction();
        try {
            $time = date('Y-m-d H:i:s');
            $product->status = $status;
            $product->shelves_time = $time;
            if ($status == 1) {
                $product->price = $price;
                $product->coin_id = $coinId;
                $product->chain_id = $coin->chain_id;
                $product->shelves_time = date('Y-m-d H:i:s');
                //产品上价加密参数
                ProductSign::query()->insertGetId([
                    'product_id' => $id,
                    'user_id' => $userInfo->id,
                    'user_address' => $userInfo->address,
                    'order_hash' => $orderHash,
                    'order_structure' => $orderStructure,
                    'order_sign' => $orderSign,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
            //产品动态
            $dynamicId = ProductDynamic::query()->insertGetId([
                'product_id' => $id,
                'event' => $status == 1 ? 2 : 6,
                'comefrom' => $userInfo->address,
                'coin_id' => $coinId,
                'coin_token' => $coinToken,
                'price' => $price,
                'hash' => $hash,
                'created_at' => $time,
                'updated_at' => $time,
            ]);

            if (!$product->save() || !$dynamicId) {
                Db::rollBack();
                $this->throwExp(400, __('validation.fail_to_edit'));
            }
            Db::commit();
        } catch (\Throwable $ex) {
            Db::rollBack();
            $this->throwExp(400, __('validation.fail_to_edit'));
        }
        return $this->successByMessage(__('validation.success_to_edit'));
    }

    public function myFollow(RequestInterface $request)
    {
        $params = $request->all();
        if (!isset($params['address']) || !$params['address'])
            $this->throwExp(400, __('validation.not_empty', ['title' => 'address']));
        $params['follow'] = 1;
        $list = (new Product())->getList($params);
        return $this->success($list);
    }

    /**
     * 商品关注
     * @return ResponseInterface
     */
    public function follow()
    {
        $userInfo = UserService::getInstance()->getUserInfoByToken();
        $product_id = $this->request->input('product_id', 0);
        if (!$product_id) {
            $this->throwExp(400, __('validation.not_empty', ['title' => 'product_id']));
        }
        $res = MemberFollow::createOrupdate($userInfo->id, $userInfo->address, $product_id);
        return $res ? $this->successByMessage("操作成功") : $this->throwExp(400, "操作失败");
    }

    /**
     * 商品排名
     * @return ResponseInterface
     */
    public function ranking_list()
    {
        $input = $this->request->all();
        $page = $this->request->input('page', 1);
        $size = $this->request->input('size', 20);
        $list = CollectionTradeTotal::rankingList($input, $page, $size);
        return $this->success($list);
    }


    /**
     * Notes:'
     * {
     * "bundle": [{
     * "token": "0x5c054252Ccd0A5FaDeb2661042729E7448898fe7",
     * "tokenId": 1,
     * "amount": 1,
     * "kind": 1,
     * "uri":""
     * }],
     * "op": 1,
     * "orderId": 1,
     * "orderHash": "0xd171b9cc81588526380a15812d90c6c89a47bf92eb844b56208da6975fcc114c",
     * "signer": "0xce2fd7544e0b2cc94692d4a704debef7bcb61328",
     * "txDeadline": 10000000000,
     * "salt": "0x66386262383631362d303939382d343332322d613861312d3139386237633564",
     * "caller": "0xce2fd7544e0b2cc94692d4a704debef7bcb61328",
     * "currency": "0x0000000000000000000000000000000000000000",
     * "price": 10000000000,
     * "fee": {
     * "royaltyRate" : 0,
     * "royaltyAddress" : "0x0000000000000000000000000000000000000000",
     * "feeRate" : 50000,
     * "feeAddress" : "0xce2fd7544e0b2cc94692d4a704debef7bcb61328"
     * }
     * }'
     * User: Deycecep
     * DateTime: 2022/4/22 14:47
     * @return array|string|void
     * @throws \App\Exception\Handler\BusinessException
     */

    public function sign()
    {
        $postData = $this->request->input('sign_structure');
        $postData = json_decode($postData, true);
        if (!is_array($postData))
            $this->throwExp(400, __('validation.illegal', ['title' => 'params']));

        $collection = Collection::getOneByWhere(['contract' => $postData['bundle'][0]['token']]);
        if (empty($collection))
            $this->throwExp(400, __('validation.illegal', ['title' => 'contract address']));

        $productId = $this->request->input('product_id');
        if (!$productId)
            $this->throwExp(400, __('validation.not_empty', ['title' => 'product_id']));

        $product = Product::getOneByWhere(['id' => $productId]);
        if (empty($product))
            $this->throwExp(400, __('validation.illegal', ['title' => 'product_id']));

        $orderHash = $postData['orderHash'];
        if (empty($orderHash)) {
            $this->throwExp(400, __('validation.not_empty', ['title' => 'product_id']));
        }

        $coin = Coin::getOneByWhere(['id' => $product->coin_id]);
        if (empty($coin))
            $this->throwExp(400, __('validation.illegal', ['title' => 'coin']));

        $userInfo = UserService::getInstance()->getUserInfoByToken();
        if ($postData['op'] == '1') {
            if ($product->owner_id == $userInfo->id)
                $this->throwExp(400, __('validation.not_buy_self_product'));
            if ($product->status == 0)
                $this->throwExp(400, __('validation.product_not_on_the_shelf'));
        }

        unset($postData['product_id']);

        //拼凑结构体
        $orderId = Order::query()->insertGetId([
                'product_id' => $productId,
                'coin_id' => $product->coin_id,
                'price' => $product->price,
                'status' => 0,
                'user_id' => $userInfo->id,
                'type' => $postData['op'] == '1' ? 'order' : 'on_shelf',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'hash' => $orderHash
            ]
        );
        if (!$orderId)
            $this->throwExp(400, __('validation.illegal', ['title' => 'params']));

        $postData['orderId'] = $orderId;

        $salt = uniqid();
        $salt = str_pad($salt, 32, '0', STR_PAD_LEFT);
        $salt = bin2hex($salt);

        $postData['signer'] = UtilsService::SIGNER;
        $postData['salt'] = '0x' . $salt;//'0x3030303030303030303030303030303030303036323636363336646630396139';
        $postData['txDeadline'] = '10000000000';
        $postData['currency'] = '0x0000000000000000000000000000000000000000';
        $postData['price'] = $this->_ethToWei($product->price);

        $postData['fee'] = [
            'royaltyRate' => $collection->copyright_fee * 100 * 10000,//版权费
            'royaltyAddress' => $collection->copyright_url,//收取版权费地址
            'feeRate' => $coin->trade_fee * 100 * 10000,//交易服务费
            'feeAddress' => $coin->contract,//版权费
        ];
        $sign = UtilsService::getInstance()->sign($postData);

        OrderSign::query()->insertGetId([
                'product_id' => $productId,
                'order_structure' => json_encode($postData),
                'op' => $postData['op'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );
        unset($postData['bundle']);
        $orderStructure = ProductSign::where('product_id', $productId)->orderByDesc('id')->value('order_structure');
        return $this->success([
            'sign' => $sign,
            'detail' => $postData,
            'order' => json_decode($orderStructure, true)
        ]);
    }

    protected function _ethToWei($value, $decimals = 18)
    {
        return bcmul((string)$value, (string)pow('10', $decimals));
    }

    /**
     * Notes: 下单
     * User: Deycecep
     * DateTime: 2022/4/22 18:03
     * @return ResponseInterface
     * @throws \App\Exception\Handler\BusinessException
     */
    public function buy()
    {
        $params = [
            'product_id' => $this->request->input('product_id'),
            'coin_id' => $this->request->input('coin_id'),
        ];
        $rules = [
            'product_id' => 'required',
            'coin_id' => 'required',
        ];
        $message = [
            'product_id.required' => ' product_id missing',
            'coin_id.required' => ' coin_id missing',
        ];
        $this->verifyParams($params, $rules, $message);

        $userInfo = UserService::getInstance()->getUserInfoByToken();
        $product = Product::getOneByWhere(['id' => $params['product_id']]);
        if (empty($product))
            $this->throwExp(400, __('validation.illegal', ['title' => 'product']));

        if ($product->owner_id == $userInfo->id)
            $this->throwExp(400, __('validation.not_buy_self_product'));

        //最近销售时间
        $product->sold_time = date('Y-m-d H:i:s');
        $product->save();

        $coinId = $this->request->input('coin_id') ?? '0';
        $coin = Coin::getOneByWhere(['id' => $coinId]);
        if (empty($coin))
            $this->throwExp(400, __('validation.illegal', ['title' => 'coin']));

        $orderId = Order::query()->insertGetId([
                'product_id' => $params['product_id'],
                'coin_id' => $params['coin_id'],
                'price' => $product->price,
                'status' => 0,
                'user_id' => $userInfo->id,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );
        return $this->success(['order_id' => $orderId]);
    }

    /**
     * Notes:购买回调
     * User: Deycecep
     * DateTime: 2022/4/24 14:10
     * @return ResponseInterface
     * @throws \App\Exception\Handler\BusinessException
     */
    public function callback()
    {
        $data = $this->request->input('encrypt');
        $data = $this->decrypt_by_private($data);

        $order = Order::getOneById($data['order_id']);
        if (!empty($order->hash))
            return $this->successByMessage(__('validation.callback_successfully'));

        if (!isset($data['product_id']))
            $this->throwExp(400, __('validation.illegal', ['title' => 'params']));

        $product = Product::getOneByWhere(['id' => $data['product_id']]);
        if (empty($product))
            $this->throwExp(400, __('validation.illegal', ['title' => 'product']));

        $coinToken = $data['coin_token'];
        $coinId = $data['coin_id'];
        $coin = Coin::getOneByWhere(['id' => $coinId]);
        if (empty($coin) || empty($coinToken))
            $this->throwExp(400, __('validation.illegal', ['title' => 'coin']));

        if (empty($data['hash']))
            $this->throwExp(400, __('validation.not_empty', ['title' => 'hash']));

        $userInfo = UserService::getInstance()->getUserInfoByToken();
        Db::beginTransaction();
        try {
            $product->status = 0;
            $product->sales += $product->price;
            $product->sales_nums += 1;
            $product->chain_id = $coin->chain_id;
            $product->owner = $userInfo->address;
            $product->owner_id = $userInfo->id;
            //产品动态
            $dynamicId = ProductDynamic::query()->insertGetId([
                'product_id' => $data['product_id'],
                'event' => 4,
                'comefrom' => $data['owner'],
                'reach' => $userInfo->address,
                'coin_id' => $coinId,
                'price' => $product->price,
                'coin_token' => $coinToken,
                'hash' => $data['hash'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $orderStatus = Order::where('id', $data['order_id'])->update(['status' => 1]);
            if (!$product->save() || !$dynamicId || !$orderStatus) {
                Db::rollBack();
                $this->throwExp(400, __('validation.callback_fail'));
            }
            Db::commit();
        } catch (\Throwable $ex) {
            Db::rollBack();
            $this->throwExp(400, __('validation.callback_fail'));
        }
        return $this->successByMessage(__('validation.callback_successfully'));
    }

    /**
     * 币价
     * @return string
     */
    public function getBinancePrice()
    {
        $price = Redis::getInstance()->hget('binance_price', 'ETHUSDT');
        return $this->success(['price' => $price]);
    }

    /**
     * 公钥加密
     * @param $str 要加密的字符串
     * @param $public_key 公钥
     * @return string base64编码后的字符串
     */
    function encrypt_by_public()
    {
        $arr = [
            'product_id' => 728,
            'coin_id' => 1,
            'coin_token' => 222
        ];
        $str = $this->request->input('str');
        $str = json_encode($arr);
        $public_key = Common::PUBLIC_KEY;
        $return_en = openssl_public_encrypt($str, $crypted, $public_key);
        if (!$return_en) {
            return ('加密失败,请检查RSA秘钥');
        }
        $str_encrypt_base64 = base64_encode($crypted);
        return $this->success(['str_encrypt_base64' => $str_encrypt_base64]);
    }

    /**
     * Notes:私钥解密
     * User: Deycecep
     * DateTime: 2022/4/26 14:24
     * @param $str
     * @return mixed
     * @throws \App\Exception\Handler\BusinessException
     */
    function decrypt_by_private($str)
    {
        $private_key = Common::PRIVATE_KEY;
        $return_de = openssl_private_decrypt(base64_decode($str), $decrypted, $private_key);
        if (!$return_de) {
            $this->throwExp(400, __('validation.illegal_operation'));
        }
        return json_decode($decrypted, true);
    }

    /**
     * 刷新NFT数据
     * @return ResponseInterface
     */
    public function refresh()
    {
        $id = $this->request->input('id', 0);
        $contract = $this->request->input('contract', '');
        $tokenID = $this->request->input('tokenID', 0);
        return $this->success((new NftReptileService())->refresh($id, $contract, $tokenID));
    }
}
