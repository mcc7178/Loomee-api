<?php

namespace App\Services;

use App\Model\Chain;
use App\Model\Coins;
use App\Model\UserRechargeAddressReserved;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\DbConnection\Db;
use Hyperf\Redis\Redis;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Di\Annotation\Inject;

class CoinService extends BaseService
{

    public $API_IP = 'http://www.bcmanage.pro/api/';
    const RECHARGE_ADDRESS = 'newAddress';
    const RECHARGE_LOG = 'rechargeLog';
    const WITHDRAW_URL = 'transfer';
    const PLATFORM = 'fll';

    /**
     * @Inject()
     * @var StdoutLoggerInterface
     */
    private static $logger;

    public function __construct()
    {
        //TODO 环境变量配置
        $this->API_IP = env('APP_DEBUG') ? 'http://www.bcmanage.pro/api/' : 'http://symbol.bcmanage.pro/api/';
    }

    // 获取充币地址
    public static function getCoinAddressByChainId($chain)
    {
        switch ($chain) {
            case "trc20":
            case "trx":
                $symbol = 'trx';
                break;

            case 'eth':
            case 'erc20':
                $symbol = 'erc20';
                break;
            case 'fil':
                $symbol = 'fil';
                break;
            default:
                return false;
        }

        $chain_id = Chain::query()->where('name', $chain)->value('id');

        $count = UserRechargeAddressReserved::query()
            ->where('status', 0)
            ->where('chain_id', $chain_id)
            ->count() ?: 0;
        if ($count < 20) {
            $params = [
                'chain' => $chain,
                'platform' => self::PLATFORM,
            ];
            $self = new self();
            $url = $self->API_IP . self::RECHARGE_ADDRESS;

            for ($i = 0; $i < 20; $i++) {
                $res = self::requestApi($url, $params, 1);
                if ($res['status'])
                    $res = $res['data']['address'];
                else {
                    self::$logger->info('获取地址失败。。。。' . json_encode($res['data'], 256));
                    return false;
                }
                $data = [
                    'address' => $res,
                    'symbol' => $symbol,
                    'created_time' => time(),
                    'chain_id' => $chain_id,
                ];
                UserRechargeAddressReserved::query()->insert($data);
            }
        }
    }


    /**
     * 充币记录
     * @return bool
     */
    public static function getUserCoinIntoList()
    {
        $self = new self();
        $url = $self->API_IP . self::RECHARGE_LOG;
        $count = DB::table('recharge_log')->where('api_id', '>=', 1)->count();
        if (!$count)
            $count = 0;

        $param = [
            'limit' => $count
        ];

        self::$logger->info('recharge: limit' . $count);
        return self::requestApi($url, $param, 1)['data'];
    }

    /**
     * request
     * @param      $url
     * @param      $params
     * @param int $is_post
     * @param bool $isOnChain
     * @return mixed
     */
    public static function requestApi($url, $params, $is_post = 1, $isOnChain = false)
    {
        $ch = curl_init();
//        var_dump($url);
//        echo "<br/>\n\t";*/
        $params['timestamp'] = time();
        $params['platform'] = self::PLATFORM;
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'JuheData');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_ENCODING, "");

        if ($is_post) {
            $header = [
                'sign:' . self::sign($params),
//                'Authorization:' . base64_encode('home:' . md5('home.' . base64_encode(json_encode($params)) . '.11111')),
            ];
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_POSTFIELDS, ($params));

            /*var_export(json_encode( $params ));
            echo "<br/>\n\t";*/
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_TIMEOUT, 21);
        } else {
            if ($params) {
                curl_setopt($ch, CURLOPT_URL, $url . '?' . $params);
            } else {
                curl_setopt($ch, CURLOPT_URL, $url);
            }
        }
        $response = curl_exec($ch);
        if ($response === false) {
            return [
                'status' => false,
                'data' => "curl_error:URL:" . $url . ",请求参数:" . json_encode($params, 256) . curl_errno($ch) . "," . curl_error($ch)
            ];
        }

        $response = json_decode($response, true);
        self::$logger->info('coinserver:' . json_encode($response));

        if ($response['message']) {
            return [
                'status' => false,
                'data' => $response['message']
            ];
        }

        if ($response['status'] !== 200) {
            return [
                'status' => false,
                'data' => $response['msg']
            ];
        }

        return [
            'status' => true,
            'data' => $response['data']
        ];
    }

    /**
     * 获取币种精度
     * @param $symbol
     * @return mixed
     * @throws \Exception
     */
    public static function getSymbolDecimals($symbol)
    {
        $symbol = strtolower($symbol);
        $conf = self::getSymbolConf($symbol);
        return $conf->decimals;
    }


    /**
     * 币种配置 - obj
     * @param $symbol
     * @throws \Exception
     */
    public static function getSymbolConf($symbol)
    {
        $redis = \App\Utils\Redis::getInstance('index4');
        if ($redis->hExists('symbol_conf', $symbol)) {
            $conf = $redis->hGet('symbol_conf', $symbol);
            $conf = json_decode($conf);
        } else {
            $conf = Coins::query()->where('symbol', $symbol)->first();
            $redis->hSet('symbol_conf', $symbol, json_encode($conf));
        }
        return $conf;
    }

    public static function sign($params)
    {
        $signKey = 'bcManage';
//        var_dump($params);
        $paramString = '';
        foreach ($params as $key => $value) {
            if (is_null($value) || $value === '' || $key == 'sign') {
                continue;
            }
            $paramString .= $key . '=' . $value . '&';
        }
        $paramString = substr($paramString, 0, -1);
//        var_dump($paramString);

        return base64_encode(hash_hmac("sha1", $paramString, $signKey, $raw_output = TRUE));
    }


}
