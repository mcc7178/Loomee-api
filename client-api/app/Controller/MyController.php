<?php

namespace App\Controller;

use App\Constants\StatusCode;
use App\Model\Product\Product;
use App\Service\Auth\UserService;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class MyController extends AbstractController
{
    /**
     * 我的NFT
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    public function product_list(RequestInterface $request)
    {
        $params = $request->all();
        if (!isset($params['address']) || !$params['address'])
            $this->throwExp(400, __('validation.required', ['attribute' => 'address']));

        $params['own'] = 1;
        $list = (new Product())->getList($params);
        return $this->success($list);
    }

    /**
     * 个人中心-产品详情
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    public function product_detail(RequestInterface $request)
    {
        $id = (int)$request->input('id', 0);
        $address = $this->request->input('address');
        if (!$address)
            $this->throwExp(400, __('validation.required', ['attribute' => 'address']));
        $userInfo = UserService::getInstance()->getUserInfoByAddress($address);
        if (!$userInfo)
            $this->throwExp(StatusCode::ERR_USER_ABSENT);

        $detail = Product::detail($id, $userInfo->id);
        return $this->success($detail);
    }

    /**
     * 我的关注
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    public function my_follow(RequestInterface $request)
    {
        $params = $request->all();
        if (!isset($params['address']) || !$params['address'])
            $this->throwExp(400, __('validation.required', ['attribute' => 'address']));
        $params['follow'] = 1;
        $list = (new Product())->getList($params);
        return $this->success($list);
    }
}