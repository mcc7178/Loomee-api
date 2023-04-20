<?php

namespace App\Services;

use App\Model\Ads;
use App\Model\AdsCategory;
use App\Model\OtcTransfer;
use App\Model\User;
use App\Model\UserRealAuth;
use Illuminate\Support\Facades\Log;

class OtcService extends BaseService
{

    const URL           = 'https://cmobile.troam.io/c2cbackend/api/';
    const CHANNEL_CODE  = 'UUEX-085';
    const API_KEY       = 'e39510f8-a73f-11ea-8120-00163e049505';
    const JUMP_URL_H5   = 'https://cmobile.troam.io/#/home?token=';//测试
    const JUMP_URL_PC   = 'https://c2cweb.troam.io/#/home?token=';//测试


    /**
     * 获取 token
     * @param $userId
     * @return mixed
     */
    public static function getToken($userId)
    {
        $url = self::URL . 'user/token';
        $authInfo = UserRealAuth::query()->where('userid', $userId)->first();
        if (!$authInfo || $authInfo['status'] != 1 || $authInfo['type'] == 3) {
            return false;
        }
        $userInfo = User::query()->with('state')->find($userId);
        $param = [
            'channelCode' => self::CHANNEL_CODE,
            'userName' => $authInfo->name,
            'idCard' => $authInfo->number,
            'mobile' => $userInfo->phone,
            'areaCode' => $userInfo->state->code,
            'timestamp' => self::getTimestamp(),
            'apiKey' => self::API_KEY
        ];
        self::getSign($param);
        $param['credentialType'] = $authInfo['type'] == 1 ? '00' : '01';//证件类型  00 身份证  / 01 护照
        $res = self::curlRequest($url, $param, 1);
        if (!$res || $res['resultCode'] != 200) {
            \Log::info('获取 OTC token 失败:' . json_encode($res, JSON_UNESCAPED_UNICODE));
            return false;
        }

        return $res['data']['token'];
    }

    /**
     * 获取跳转页 url
     * @param $userId
     * @return array
     */
    public static function getHomeUrl($userId)
    {
        $token = self::getToken($userId);
        if (!$token) {
            return false;
        }
        $timestamp = self::getTimestamp();
        $param = [$timestamp, $token, self::API_KEY];
        self::getSign($param);

        return [
            'pc' => self::JUMP_URL_PC . $token . '&timestamp=' . $timestamp . '&sign=' . $param['sign'],
            'h5' => self::JUMP_URL_H5 . $token . '&timestamp=' . $timestamp . '&sign=' . $param['sign']
        ];
    }


    /**
     * 转入法币
     * @param $token
     * @param $coin
     * @param $quantity
     * @return mixed
     */
    public static function transferIn($token, $coin, $quantity, $userId)
    {
        $token = self::getToken($userId);
        if (!$token) {
            return false;
        }
        $url = self::URL . 'transfer/in';
        $param = [
            'orderNo' => self::getOrderNo(),
            'coin' => $coin,
            'quantity' => $quantity,
            'timestamp' => self::getTimestamp(),
            'apiKey' => self::API_KEY
        ];
        self::getSign($param);
        $param['token'] = $token;
//        $res = self::curlRequest($url, $param, 1);
        $res['resultCode'] = 200;
        if ($res['resultCode'] == 200) {
            $param['type'] = 1;
            $param['userid'] = $userId;
            $res = self::saveTransferOrder($param);
        } elseif ($res['resultCode'] == 405) {
            $userId = 1;//redis 查
            $token = self::getToken($userId);
            self::transferIn($token, $coin, $quantity);
        }
        return $res;
    }


    /**
     * 法币转出 (法币到币币)
     * @param $token
     * @param $coin
     * @param $quantity
     * @return mixed
     */
    public static function transferOut($token, $coin, $quantity)
    {
        $url = self::URL . 'transfer/out';

        $param = [
            'orderNo' => self::getOrderNo(),
            'coin' => $coin,
            'quantity' => $quantity,
            'timestamp' => self::getTimestamp(),
            'apiKey' => self::API_KEY
        ];
        self::getSign($param);
        $param['token'] = $token;
        $res = self::curlRequest($url, $param, 1);
        if ($res['resultCode'] == 200) {
            // 订单入库
            $param['type'] = 2;
            $res = self::saveTransferOrder($param);
        }
        return $res;
    }

    /**
     * 订单入库 码放南山
     * @param $param
     * @return mixed
     */
    public static function saveTransferOrder($param)
    {
        $id = OtcTransfer::insertGetId([
            'symbol' => $param['coin'],
            'quantity' => $param['quantity'],
            'created_at' => time(),
            'userid' => $param['userid'],
            'type' => $param['transferType'],
            'order_number' => $param['transferNum'],
            'status' => 'success',
        ]);
//        $OtcTransfer->symbol = $param['coin'];
//        $OtcTransfer->quantity = $param['quantity'];
//        $OtcTransfer->created_at = time();
//        $OtcTransfer->userid = $param['userid'];
//        $OtcTransfer->type = $param['type'];
//        $id = $OtcTransfer->save();
//        $OtcTransfer = OtcTransfer::find($id);
//        $OtcTransfer->order_number = self::getOrderNo($id);
//        $res = $OtcTransfer->save();
        return $id;
    }

    /**
     * 资产划转订单状态
     * @param $orderNos '' 多订单号以 | 隔开 例如 123|321
     * @return mixed
     */
    public static function transferStatus($orderNos)
    {
        $url = self::URL . 'transfer/status';

        $param = [
            'channelCode' => self::CHANNEL_CODE,
            'timestamp' => self::getTimestamp(),
            'apiKey' => self::API_KEY
        ];
        self::getSign($param);
        $param ['orderNos'] = $orderNos;
        return self::curlRequest($url, $param, 1);
    }

    /**
     * 资金划转回调
     * @param $request
     * @return bool|string
     */
    public static function transferCallback($request)
    {

    }

    /**
     * @return bool|string
     */
    public static function getTransferStatusCallback()
    {
        return parent::getLastError(); // TODO: Change the autogenerated stub
    }


    /**
     * @param $url
     * @param bool $params
     * @param int $isPost
     * @return mixed|string
     */
    public static function curlRequest($url, $params = false, $isPost = 0)
    {
        $httpInfo = [];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.22 (KHTML, like Gecko) Chrome/25.0.1364.172 Safari/537.22');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        if ($isPost) {
            $header = ['Content-Type:application/json;charset=utf-8;'];
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
            curl_setopt($ch, CURLOPT_URL, $url);
        } else {
            if ($params) {
                curl_setopt($ch, CURLOPT_URL, $url . '?' . $params);
            } else {
                curl_setopt($ch, CURLOPT_URL, $url);
            }
        }
        $response = curl_exec($ch);
        if ($response == FALSE) {
            return "cURL Error: " . curl_error($ch);
        }
        curl_close($ch);
        return json_decode($response, true);
    }

    public static function getSign(&$params)
    {
        $params['sign'] = MD5(implode('', $params));
        unset($params['apiKey']);
    }

    public static function getTimestamp()
    {
        list($micro, $sec) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($micro) + floatval($sec)) * 1000);
    }

    /**
     * @param $orderId
     * @return string
     */
    public static function getOrderNo($orderId = 0)
    {
        return date('YmdHis') . str_pad($orderId, 6, '0', STR_PAD_LEFT);
    }
}