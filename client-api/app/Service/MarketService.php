<?php

namespace App\Services;
use App\Lib\Push;
use App\Model\Markets;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use App\Services\CommonService;
use App\Model\UserFavorite;
use App\Lib\Redis as LibRedis;

class MarketService extends BaseService{

    public static $usdt_rmb_price_key = 'usdt_rmb_price';

    public static function getIndexMarkets($num,$order_cloumn,$order_type,$is_index = 0){

        $query = Markets::whereIn('is_enabled',[1, 2] );
        if($is_index){
            $query->where('is_index',1);
        }



        $result = $query->select(
                    'id',
                    'name',
                    'change',
                    'price',
                    'decimals_currency',
                    'decimals_number',
                    'decimals_price',
                    'to_coin'
                )->orderBy($order_cloumn,$order_type)
                ->offset(0)->limit($num)
                ->get()->toArray();
        return $result;
    }

    //更新计价币种最新价
    public static function refreshCoinRmbPrice($coin){
        $coin = strtolower($coin);
        if($coin == 'usdt'){
            return self::rmbForUsdt();
        }

        //本站查找以此币种为买卖币种的交易对
        $market = $coin.'/usdt';
        $market = Markets::where('name',$market)->first();
        if(!$market){
            $market = Markets::where('name','like',$coin.'/%')->first();
        }
        if($market){
            $market_arr = explode('/',$market->name);
            if(count($market_arr) != 2){
                return 0;
            }
            //从缓存读取最新价
            $market_new_price = KlineService::getNewPriceByCache($market->name);
            $price = self::refreshCoinRmbPrice($market_arr[1]);
            return $price*$market_new_price;
        }
        //从火币拿
        $price = 0;
        $url = "https://api.huobi.pro/market/detail/merged?symbol={$coin}usdt";
        // 对应usdt的价格
        $data = CommonService::curl_request($url);
        $data = json_decode($data, true);
        if($data['status'] === 'ok'){
            $price = bcmul($data['tick']['close'], self::rmbForUsdt(), 8);
        }
        return $price;
    }


    public static function getUserFavorite($user_id){
        return UserFavorite::where('userid',$user_id)->get()->toArray();
    }

    //获取计价币种人民币价格
    public static function getCoinRmbPriceByCache($coin){
        $coin = strtolower($coin);
        $price = Redis::hget('rmb_price',$coin);
        if(!$price){
            return 0;
        }
        return $price;
    }

    //获取计价币种人民币价格
    public static function getAllCoinRmbPriceByCache(){
        $price = Redis::hgetall('rmb_price');
        if(!$price){
            return [];
        }
        return $price;
    }

    //获取人民币对美元汇率
    public static function rmbForUsdt(){

//        $price = Redis::get(self::$usdt_rmb_price_key);
//        if($price){
//            return $price;
//        }

//        $url = "https://otc-api.huobi.co/v1/data/trade-market?coinId=2&currency=1&tradeType=sell&currPage=1&payMethod=0&country=37&blockType=general&online=1&range=0&amount=";
//        $url = "https://otc-api-hk.eiijo.cn/v1/data/trade-market?coinId=2&currency=1&tradeType=sell&currPage=1&payMethod=0&acceptOrder=-1&country=&blockType=general&online=1&range=0&amount=";
//        $data = CommonService::curl_request($url);
        $url = 'https://www.pexpay.com/bapi/c2c/v1/friendly/c2c/ad/search';
        $x = '{
            "page": 1,
            "rows": 1,
            "payTypes": [],
            "classifies": [],
            "asset": "USDT",
            "tradeType": "BUY",
            "fiat": "CNY",
            "publisherType": null,
            "filter": {
                "payTypes": []
            }
        }';
        $params = (json_decode($x, true));
        $data = self::requestApi($url, $params);
        $price = 6.2;
        if ($data['success'] === true)
        {
            $price = ($data['data'][0]['adDetailResp']['price']);
            Log::info('pexpay...'. $price);
        }
        Redis::set(self::$usdt_rmb_price_key,  $price);
        if ($price && $price > 0)
        {
            Log::info('market_cny_price...'. $price);
            Redis::set('market_cny_price',$price);
        }
        return $price > 0 ? $price : 6.33;
    }

    /**
     * 更新市场的最新信息
     * @param $market
     * @param $price
     * @param $quantity
     * @return bool
     */
    public static function changeMarketInfo($market, $price, $quantity, $w = false)
    {
        $market = strtolower($market);
        if( LibRedis::getInstance()->hExists('trade_market', $market)){
            $data = LibRedis::getInstance()->hGet('trade_market', $market);
            $data = json_decode($data,true);
            $data['h'] = self::h($market, $price);
            $data['l'] = self::l($market, $price);
            $data['p'] = $price;
            $data['v'] =  bcadd($data['v'], $quantity, 2);
            $data['r'] = self::change($market, $price);
            $data['cny'] = self::rmb($market, $price);
            $data['m'] = $market;
            $data['t'] = time();
            LibRedis::getInstance()->hSet('trade_market', $market, json_encode($data));
        }
        else
        {
            $data['h'] = self::h($market, $price);
            $data['l'] = self::l($market, $price);
            $data['p'] = $price;
            $data['v'] =  bcadd(0, $quantity, 2);
            $data['r'] = self::change($market, $price);
            $data['cny'] = self::rmb($market, $price);
            $data['t'] = time();
            $data['m'] = $market;
            LibRedis::getInstance()->hSet('trade_market', $market, json_encode($data));
        }


        Push::sendToChannel('all_quote', ['data' => $data,'type' => 'all_quote']);
        $data['w'] = $w;
        Push::sendToChannel($data['m'], ['data' => $data,'type'=>'quote']);
        return true;
    }

    /**
     * 每天第一条价格
     * @param $market
     * @param $price
     * @return string
     * @throws \Exception
     */
    public static function everydayOnePrice($market, $price)
    {
        $market = strtolower($market);
        $time = date("Y-m-d");
        if( LibRedis::getInstance(4)->hExists($market.'-everyday-one-price', $time)){
            return LibRedis::getInstance(4)->hGet($market.'-everyday-one-price', $time);
        }
        LibRedis::getInstance(4)->hSet($market.'-everyday-one-price', $time, $price);
        return $price;
    }

    /**
     * 更新涨跌幅
     * @param $market
     * @param $price
     * @return string|null
     * @throws \Exception
     */
    public static function change($market, $price)
    {
        $onePrice = self::everydayOnePrice($market, $price);
        $diff = bcsub($price, $onePrice, 8);
        return bcmul(bcdiv($diff, $onePrice, 4), 100, 2);
    }

    /**
     * 最高价
     * @param $market
     * @param $price
     * @return mixed
     * @throws \Exception
     */
    public static function h($market, $price)
    {
        $k = 'everyday-high-price:';
        $market = strtolower($market);
        $time = date("Y-m-d");
        if( LibRedis::getInstance(4)->hExists($k.$market, $time)){
            $p = LibRedis::getInstance(4)->hGet($k.$market, $time);
            if ($p < $price){
                LibRedis::getInstance(4)->hSet($k.$market, $time, $price);
                return $price;
            }
            return $p;
        }
        LibRedis::getInstance(4)->hSet($k.$market, $time, $price);
        LibRedis::getInstance(4)->expire($k.$market, 24*60*60);
        return $price;
    }

    /**
     * 最低价
     * @param $market
     * @param $price
     * @return mixed
     * @throws \Exception
     */
    public static function l($market, $price)
    {
        $k = 'everyday-low-price:';
        $market = strtolower($market);
        $time = date("Y-m-d");
        if( LibRedis::getInstance(4)->hExists($k.$market, $time)){
            $p = LibRedis::getInstance(4)->hGet($k.$market, $time);
            if ($p > $price){
                LibRedis::getInstance(4)->hSet($k.$market, $time, $price);
                return $price;
            }
            return $p;
        }
        LibRedis::getInstance(4)->hSet($k.$market, $time, $price);
        LibRedis::getInstance(4)->expire($k.$market, 24*60*60);
        return $price;
    }

    /**
     * 获取当前rmb价格
     * @param $market
     * @param $price
     * @return int
     */
    public static function rmb($market, $price)
    {
        // 获取交易区价格
        $market = strtolower($market);
        list($symbol, $block) = explode('/', $market);
        if( LibRedis::getInstance()->hExists('rmb_price', $block) ){
            $p = LibRedis::getInstance()->hGet('rmb_price', $block);
        }else{
            $p = 0;
        }
        $price = bcmul($p, $price, 4);
        return $price;
    }


    public static function getMarketByCache($market = null){
        Redis::select(0);
        if($market){
            return Redis::hget('trade_market',$market);
        }
        return Redis::hgetall('trade_market');
    }

    //获取剩余交易对
    /**
     * Undocumented function
     *
     * @param array $markets 被排除的交易对名
     * @param string $order 排序方式
     * @param int $is_index 是否首页
     * @param int $num 数量
     * @param int $order_cloumn 排序字段
     * @param int $order_type 排序类型
     * @return void
     */
    public static function getMarketByDb($is_index,$num,$order_cloumn,$order_type,array $markets = []){
        $query = Markets::where('is_enabled',1);
        if($is_index){
            $query->where('is_index',1);
        }
        // if($order_cloumn == 'change'){
        //     if($order_type == 'asc'){
        //         $query->where('change','<',0);
        //     }else{
        //         $query->where('change','>',0);
        //     }
        // }
        if($markets){
            $query->whereNotIn('name',$markets);
        }
        $result = $query->orderBy('sort','desc')
                ->select(
                    'id',
                    'name',
                    'change',
                    'price',
                    'decimals_currency',
                    'decimals_number',
                    'decimals_price',
                    'to_coin',
                    'volume'
                )->orderBy($order_cloumn,$order_type)
                ->offset(0)->limit($num)
                ->get()->toArray();
        return $result;
    }


    public static function getMarketAccuracy(array $markets){
        return Markets::whereIn('name',$markets)->select('name','decimals_number','decimals_price','decimals_currency')->get()->toArray();
    }


    /**
     * market-conf
     * @param $market
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|string|null
     * @throws \Exception
     */
    public static function getMarketConf($market)
    {
        $k = 'market_conf';
        $market = strtolower($market);
        if (\App\Lib\Redis::getInstance(4)->hExists($k, $market)){
            return json_decode(\App\Lib\Redis::getInstance(4)->hGet($k, $market));
        }
        $marketConf = Markets::query()->where('name', $market)->first();
        \App\Lib\Redis::getInstance(4)->hSet($k, $market, json_encode($marketConf));
        return $marketConf;
    }


    public static function getAllMarketConf(){
        /*Redis::select(4);
        $k = 'market_conf';
        if ($marketConf = Redis::hgetall($k)){
            foreach($marketConf as &$item){
                $item = json_decode($item);
            }
            return $marketConf;
	}*/
        $marketConf = Markets::query()->where('is_enabled', 1)->get();
        if(!$marketConf){
            return [];
        }
        /*$_marketConf = [];
        foreach($marketConf as $market){
            $_marketConf[$market->name] = json_encode($market);
        }
	Redis::hmset($k, $_marketConf);*/
        return $marketConf;
    }

    public static function requestApi($url, $params,$is_post = 1 )
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'JuheData');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_ENCODING, "");
        $header = [
            'Content-Type: application/json',
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        if ($is_post) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_TIMEOUT, 21);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));

        } else {
            if ($params) {
                curl_setopt($ch, CURLOPT_URL, $url . '?' . $params);
            } else {
                curl_setopt($ch, CURLOPT_URL, $url);
            }
        }

        $response = curl_exec($ch);

        $response = json_decode($response, true);

        if (!$response) {
            return false;
        }
        return $response;
    }


}
