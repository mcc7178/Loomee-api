<?php

namespace App\Service;

use App\Foundation\Traits\Singleton;
use App\Service\BaseService;
use Hyperf\DbConnection\Db;
use Hyperf\Utils\Context;

class Binance
{
    use Singleton;
    private $base = 'https://api.binance.com/api/';
    private $api = '';
    private $api_method = '';



    /********** 基础信息 **********/

    public function exchangeInfo() {
        $this->api_method = "v3/exchangeInfo";
        $this->api = $this->base . $this->api_method;

        $res = $this->request($this->api);
        return json_decode($res,true);
    }

    public function getPrice($symbol) {
        $param = ["symbol"=>$symbol];//EHT/USDT  ETHUSDT

        $this->api_method = "v3/ticker/price";
        $this->api = $this->base . $this->api_method;

        $res = $this->request($this->api,$param);
        return json_decode($res,true);
    }

    /**
     * 请求
     */
    private function request($url, $params = [], $method = "GET") {
        $opt = [
            "http" => [
                "method" => $method,
                "header" => "User-Agent: Mozilla/4.0 (compatible; PHP Binance API)\r\n"
            ]
        ];
        $context = stream_context_create($opt);
        $query = http_build_query($params, '', '&');
        $res = file_get_contents($url.'?'.$query, false, $context);
        return $res;
    }

}