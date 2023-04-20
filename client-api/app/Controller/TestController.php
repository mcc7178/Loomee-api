<?php

namespace App\Controller;


use Hyperf\HttpServer\Contract\RequestInterface;

class TestController extends AbstractController
{
    public function index(RequestInterface $request, $id, $name)
    {
        echo 1;
        return __METHOD__;
    }
}