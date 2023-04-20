<?php

namespace App\Services;

use App\Model\UserAssetMinings;
use App\Model\UserBonusDynamicLog;
use App\Model\UserBonusLog;
use App\Model\Withdraw;
use App\Services\BaseService;
use App\Model\User;
use App\Model\UserLoginLog;
use App\Lib\Redis;
use App\Model\UserAssetRecharges;
use Illuminate\Support\Facades\DB;
use App\Model\UserAssetDeals;
class TranslateService extends BaseService
{
    public $request;

    /**
     * @param $lang
     * @param $string
     * @return mixed
     */
    public static function getTranslate($lang,$string)
  {
      $url = 'https://fy.iciba.com/ajax.php?a=fy&f'."=auto&t=$lang&w=$string";
//      var_dump(self::curl($url));
      return json_decode(self::curl($url),true)['content']['out'] ?? $string;
  }

    public static function curl($url,$params = '',$ispost = 0)
    {
        $httpInfo = [];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.22 (KHTML, like Gecko) Chrome/25.0.1364.172 Safari/537.22');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        if ($ispost) {
            $header = ['Content-Type:application/json;charset=utf-8;'];
            curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
            $params = is_array($params) ? json_encode($params) : $params;
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_URL, $url);
        } else {
            if ($params) {
                curl_setopt($ch, CURLOPT_URL, $url . '?' . $params);
            } else {
                curl_setopt($ch, CURLOPT_URL, $url);
            }
        }
        $response = curl_exec($ch);


        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $httpInfo = array_merge($httpInfo, curl_getinfo($ch));
        curl_close($ch);
        return $response;
    }
}
