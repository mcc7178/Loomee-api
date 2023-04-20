<?php

namespace App\Lib;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\Inject;

class Common
{
    /**
     * @Inject()
     * @var StdoutLoggerInterface
     */
    private $logger;

    public function subHex0x(string $hexString): string
    {
        if (strpos($hexString, '0x') !== false) {
            $hexString = substr($hexString, 2);
        }
        return $hexString;

    }

    public function addHex0x(string $address): string
    {
        if (strpos($address, '0x') !== false) {
            return $address;
        }
        return '0x' . $address;
    }

    public function dec2hex($dec): string
    {
        $dec = strval($dec);

        $last = bcmod($dec, 16, 0);
        $remain = bcdiv(bcsub($dec, $last, 0), 16, 0);

        if ('0' === $remain) {
            return dechex($last);
        } else {
            return $this->dec2hex($remain) . dechex($last);
        }
    }

    public function encodeAbiAddress(string $address): string
    {
        return strtolower(str_pad($this->subHex0x($address), 64, '0', STR_PAD_LEFT));
    }

    public function decodeAbiAddress(string $address): string
    {
        return strtolower($this->addHex0x(substr($this->subHex0x($address), 24)));
    }

    public function hex2dec($hex, $function = ''): string
    {
//        if ($function != 'x')
//            var_dump('死循环：'.$hex. '; $function=='. $function);
        $hex = (string)$this->subHex0x(strval($hex));
        if (strlen($hex) === 1) {
            return strval(hexdec($hex));
        } else {
            $remain = substr($hex, 0, -1);
            $last = substr($hex, -1);
            return \bcadd(\bcmul('16', $this->hex2dec($remain, 'x'), 0), $this->hex2dec($last, 'x'), 0);
        }
    }

    public function getValue($value, $d)
    {
        return bcdiv($value, pow(10, $d), $d);
    }

    public function getWei($value, $d)
    {
        return bcmul($value, pow(10, $d));
    }

    public function isAddress($address)
    {
        return boolval(preg_match('@^0x[0-9a-f]{40}$@i', $address));
    }

    public function unit2wei($value, $decimals)
    {
        return \bcmul((string)$value, (string)\bcpow(10, $decimals));
    }

    public function jsonRpc(string $url, string $method, array $params = [])
    {
        $data = [
            'id' => time(),
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params
        ];
        $header = [
            'Content-Type: application/json',
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $header,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT => 30,
        ]);

        $ret = curl_exec($ch);

        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $message = "url:$url,method:$method,params:" . json_encode($data) . ",line:" . __LINE__;
        if (0 !== $curlErrno) {
            $msg = "curl_error: msg:$curlError,errorno:$curlErrno";
            $this->logger->error($msg . $message);
        }

        if (200 !== $responseCode) {
            $msg = "request_error: code:$responseCode";
            $this->logger->error($msg . $message);
        }

        $retParse = json_decode($ret, true);
        if (!is_array($retParse)) {
            $msg = "response_type_error: code:$responseCode,ret:" . json_encode($ret) . ",responseData:" . json_encode($retParse);
            $this->logger->error($msg . $message);
        }

        if (isset($retParse['error']) && !empty($retParse['error'])) {
            $msg = "response_result_error:ret:" . json_encode($ret) . ",responseData:" . json_encode($retParse);
            $this->logger->error($msg . $message);
        }

        if (isset($retParse['result'])) {
            return $retParse['result'];
        }
    }


    public function requestApi($url, $params, $is_post = 1)
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