<?php

namespace App\Controller;

use App\Constants\StatusCode;
use App\Model\NoticeLog;
use App\Model\Product\Product;
use App\Model\Product\ProductDynamic;
use App\Model\Product\ProductOffer;
use App\Service\Auth\UserService;
use App\Service\UtilsService;
use App\Utils\Redis;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;

class OfferController extends AbstractController
{
    private $method = 'balanceOf';
    private $allow_method = 'allowance';
    private $abi = '[{"constant":true,"inputs":[],"name":"name","outputs":[{"name":"","type":"string"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"name":"guy","type":"address"},{"name":"wad","type":"uint256"}],"name":"approve","outputs":[{"name":"","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[],"name":"totalSupply","outputs":[{"name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"name":"src","type":"address"},{"name":"dst","type":"address"},{"name":"wad","type":"uint256"}],"name":"transferFrom","outputs":[{"name":"","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":false,"inputs":[{"name":"wad","type":"uint256"}],"name":"withdraw","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[],"name":"decimals","outputs":[{"name":"","type":"uint8"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[{"name":"","type":"address"}],"name":"balanceOf","outputs":[{"name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"symbol","outputs":[{"name":"","type":"string"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"name":"dst","type":"address"},{"name":"wad","type":"uint256"}],"name":"transfer","outputs":[{"name":"","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":false,"inputs":[],"name":"deposit","outputs":[],"payable":true,"stateMutability":"payable","type":"function"},{"constant":true,"inputs":[{"name":"","type":"address"},{"name":"","type":"address"}],"name":"allowance","outputs":[{"name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"payable":true,"stateMutability":"payable","type":"fallback"},{"anonymous":false,"inputs":[{"indexed":true,"name":"src","type":"address"},{"indexed":true,"name":"guy","type":"address"},{"indexed":false,"name":"wad","type":"uint256"}],"name":"Approval","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"name":"src","type":"address"},{"indexed":true,"name":"dst","type":"address"},{"indexed":false,"name":"wad","type":"uint256"}],"name":"Transfer","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"name":"dst","type":"address"},{"indexed":false,"name":"wad","type":"uint256"}],"name":"Deposit","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"name":"src","type":"address"},{"indexed":false,"name":"wad","type":"uint256"}],"name":"Withdrawal","type":"event"}]
';

    //weth合约地址
    private $contract = '0xDF3A67A46B8f2378ae8c436417E04A6d8973216D';

    //平台交易合约地址
    private $trade_contract = '0xD21051F8277FC06Cbe3e1B75f39ed0d977ADB6bf';

    /**
     * 基础信息
     * @param RequestInterface $request
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function info(RequestInterface $request)
    {
        $params = [
            'pid' => $request->input('pid'),
        ];
        $rule = [
            'pid' => 'required',
        ];
        $message = [
            'pid.required' => __('validation.required'),
        ];
        $this->verifyParams($params, $rule, $message);
        $user = UserService::getInstance()->getUserInfoByToken();
        $product = Product::query()->with([
            'collection' => function ($query) {
                $query->select(['id', 'name', 'logo']);
            }
        ])->select(['name', 'picture', 'collection_id', 'price', 'coin_id'])->findOrFail($params['pid'])->each(function ($item) {
            $item->picture = $item->picture ? env('API_URL') . $item->picture : '';
            $item->animation_url = $item->animation_url ? env('API_URL') . $item->animation_url : '';
        });
        $redis = Redis::getInstance();
        $floor_price = $redis->hGet('floor_price', "collection_{$product->collection_id}");
        $balance = $this->getBalance($user->address);
        return $this->success([
            'product' => $product,
            'floor_price' => $floor_price,
            'balance' => [
                'weth' => $balance,
                'usdt' => bcmul((string)$balance, $redis->hGet('binance_price', 'ETHUSDT'), 8)
            ]
        ]);
    }

    /**
     * 提交报价
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    public function save(RequestInterface $request)
    {
        $params = [
            'pid' => $request->input('pid'),
            'chain' => $request->input('chain'),
            'price' => $request->input('price'),
            'expire_date' => $request->input('expire_date'),
            'is_accepted' => $request->input('is_accepted'),
        ];
        $rule = [
            'pid' => 'required',
            'chain' => 'required',
            'price' => 'required|numeric',
            'expire_date' => 'required',
            'is_accepted' => 'accepted',
        ];
        $message = [
            'pid.required' => __('validation.required'),
            'chain.required' => __('validation.required'),
            'is_accepted.accepted' => __('validation.accepted'),
            'price.required' => __('validation.required'),
            'price.numeric' => __('validation.numeric'),
            'expire_date.required' => __('validation.required'),
        ];
        $this->verifyParams($params, $rule, $message);
        $user = UserService::getInstance()->getUserInfoByToken();
        $balance = $this->getAllowableBalance($user->address);
        $product = Product::query()->findOrFail($params['pid']);
        if ($params['price'] < $product->price) {
            $this->throwExp(StatusCode::ERR_OFFER_PRICE_TO_LOW);
        }
        if ($balance < $params['price']) {
            $this->throwExp(StatusCode::ERR_OFFER_BALANCE_NOT_ENOUGH, 'balance:' . $balance);
        }

        $expired_at = date('Y-m-d H:i:s', $params['expire_date']);
        Db::beginTransaction();
        try {
            //创建报价数据
            $model = new ProductOffer();
            $model->product_id = $params['pid'];
            $model->user_id = $user->id;
            $model->chain = $params['chain'];
            $model->amount = $params['price'];
            $model->from = $user->address;
            $model->expired_at = $expired_at;
            $model->status = 1;
            $model->save();

            //新增消息通知日志
            $noticeModel = new NoticeLog();
            $noticeModel->title = '报价成功';
            $noticeModel->title_en = 'Offer Success';
            $noticeModel->content = '您已经成功给出报价,请您留意平台关于此报价的通知';
            $noticeModel->content_en = 'You have successfully made the offer, please pay attention to the notification of this offer on LOOMEE notice center.';
            $noticeModel->user_id = $user->id;
            $noticeModel->source_id = $model->id;
            $noticeModel->type = 1;
            $noticeModel->save();

            //新增产品动态
            ProductDynamic::insert([
                'product_id' => $params['pid'],
                'collection_id' => $product->collection_id,
                'event' => 3,
                'comefrom' => $user->address,
                'coin_id' => $product->coin_id,
                'price' => $params['price'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            //todo 消息通知
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollBack();
            $this->throwExp(StatusCode::ERR_EXCEPTION, $e->getMessage() . ',line:' . $e->getLine());
        }
        return $this->successByMessage();
    }

    /**
     * 获取用户WETH余额
     * @param string $address
     * @return string|null
     */
    private function getBalance(string $address)
    {
        //$address = '0x67DFfE98c68292A0F386359b7e33f6BEEaeeDFAd';//0.2795
        $balance = (new UtilsService())->apiContractUrl($this->contract, $this->abi, $this->method, [$address]);
        return bcdiv($balance, '1000000000000000000', 8);
    }

    /**
     * 已授权的weth额度
     * @param string $address
     * @return string
     */
    private function getAllowableBalance(string $address)
    {
        //$address = '0x67DFfE98c68292A0F386359b7e33f6BEEaeeDFAd';//0.2795
        $balance = (new UtilsService())->apiContractUrl($this->contract, $this->abi, $this->allow_method, [$address, $this->trade_contract]);
        return bcdiv($balance, '1000000000000000000', 8);
    }

    /**
     * 报价列表
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    public function list(RequestInterface $request)
    {
        $params = [
            'pid' => $request->input('pid', 0),
            'status' => $request->input('status', 0),
            'chain_id' => $request->input('chain_id', 0),
            'address' => $request->input('address', ''),
            'page' => $request->input('page', 1),
            'size' => $request->input('size', 20),
        ];
        $list = ProductOffer::getList($params['pid'], $params['status'], $params['chain_id'], $params['address'], (int)$params['page'], (int)$params['size']);
        return $this->success($list);
    }

    /**
     * 取消报价
     * @param int $id
     * @return ResponseInterface
     */
    public function cancel(int $id)
    {
        $model = ProductOffer::findOrFail($id);
        $userInfo = UserService::getInstance()->getUserInfoByToken();
        if ($model->from != $userInfo->address) {
            $this->throwExp(400);
        }
        if (in_array($model->status, [2, 3])) {
            $this->throwExp(StatusCode::ERR_STATUS_ERROR);
        }
        $model->status = 3;
        $res = $model->save();
        if (!$res) {
            $this->throwExp(400);
        }
        return $this->successByMessage();
    }
}