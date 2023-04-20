<?php

namespace App\Services;

use App\Services\BaseService;
use Illuminate\Support\Facades\Redis;

class SmsService extends BaseService{

        // 发送验证码
        public static function sendMsg($phone,$user_id = 0, $c = 0, $check = true)
        {
            if ($c != 0)
            {
                $verify_code = $c;
            }else
            {
                $verify_code = rand(111111, 999999);
            }

            if ($check){
                $res = Redis::get('sms:'.$phone);
                if ($res) {
                    static::addError('操作过于频繁，请稍后重试',400);
                    return false;
                }
            }

            $url = 'https://open.ucpaas.com/ol/sms/sendsms';
            $data = config('sms.yunzhixun');
            $data['mobile'] = $phone;
            $data['param'] = $verify_code;
            $res = self::curlRequest($url, $data, 1);

            $res = json_decode($res);

            if ($res->code == 0) {
                $token = str_random(60);
                $code_key = 'send_sms:'.$token.$phone;
                $code_key = $user_id ? $code_key.$user_id : $code_key;
                Redis::setex($code_key, 2*60, $verify_code);
                Redis::setex('sms:'.$phone, 60, 1);

                return $token;
            } else {
                static::addError('发送失败',500);
                return false;
            }
        }
    
        public static function curlRequest($url, $params = false, $ispost = 0)
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
            if ($response === FALSE) {
                return "cURL Error: " . curl_error($ch);
            }
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $httpInfo = array_merge($httpInfo, curl_getinfo($ch));
            curl_close($ch);
            return $response;
        }
    
    
        /**
         * 验证验证短信
         * @param $captcha
         * @param $captcha_token
         * @return array
         */
        public static function checkSms($sms_code, $mobile,$token,$user_id = 0)
        {
            if (env('APP_DEBUG')){
                return true;
            }
            if(!$sms_code || !$mobile){
                static::addError('手机或验证码不存在',400);
                return false;
            }
            $key = 'send_sms:'.$token.$mobile;
            if($user_id){
                $key .= $user_id;
            }

            //     $code_key = 'send_sms:'.$token.$phone;
            //     $code_key = $user_id ? $code_key.$user_id : $code_key;

            $check = Redis::get($key);
            if ($check != $sms_code) {
                static::addError('短信验证码错误或已失效',400);
                return false;
            }
            //Redis::del($key);
            return true;
        }

    public static function sendFinanceMsg($phone, $verify_code)
    {
//
        self::sendMsg($phone, 0, $verify_code, false);
        return true;
    }
}