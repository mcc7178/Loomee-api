<?php

namespace App\Controller;

use App\Model\Collection\Collection;
use App\Service\Auth\UserService;

class CollectionController extends AbstractController
{
    public function dynamic()
    {
        $input = $this->request->all();
        $list = Collection::dynamic($input);
        return $this->success($list);
    }

    public function my_dynamic()
    {
        $input = $this->request->all();
        $address = $this->request->input('address', '');
        if (!$address)
            $this->throwExp(400, __('validation.not_empty', ['title' => 'address']));
        $list = Collection::dynamic($input);
        return $this->success($list);
    }

    public function info()
    {
        return $this->success(Collection::info($this->request->input('id')));
    }

    public function list()
    {
        $chain_id = $this->request->input('chain_id', 0);
        $cate_id = $this->request->input('cate_id', 0);
        $page = (int)$this->request->input('page', 1);
        $size = (int)$this->request->input('size', 20);
        $list = Collection::list($chain_id, $cate_id, (int)$page, (int)$size);
        return $this->success($list);
    }
}