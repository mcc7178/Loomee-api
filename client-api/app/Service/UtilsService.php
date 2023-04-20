<?php

namespace App\Service;

use App\Foundation\Traits\Singleton;
use Hyperf\DbConnection\Db;
use Hyperf\Utils\Context;

use Elliptic\EC;
use FurqanSiddiqui\Ethereum\Exception\ContractABIException;
use FurqanSiddiqui\Ethereum\Math\Integers;
use Web3\Utils;
use Web3\Web3;
use Web3p\EthereumUtil\Util;

use App\Utils\Bsc\Credential;
use App\Utils\Bsc\CustomContract;
use App\Utils\Bsc\NodeClient;

class UtilsService
{
    use Singleton;

    const CONTRACT_ABI = '[{"inputs":[],"stateMutability":"nonpayable","type":"constructor"},{"inputs":[{"components":[{"components":[{"internalType":"address","name":"token","type":"address"},{"internalType":"uint256","name":"tokenId","type":"uint256"},{"internalType":"uint256","name":"amount","type":"uint256"},{"internalType":"uint8","name":"kind","type":"uint8"}],"internalType":"struct SignTest.TokenNFT[]","name":"bundle","type":"tuple[]"},{"internalType":"uint8","name":"op","type":"uint8"},{"internalType":"uint256","name":"orderId","type":"uint256"},{"internalType":"bytes32","name":"orderHash","type":"bytes32"},{"internalType":"address","name":"signer","type":"address"},{"internalType":"uint256","name":"txDeadline","type":"uint256"},{"internalType":"bytes32","name":"salt","type":"bytes32"},{"internalType":"address","name":"caller","type":"address"},{"internalType":"contract IERC20","name":"currency","type":"address"},{"internalType":"uint256","name":"price","type":"uint256"},{"components":[{"internalType":"uint256","name":"royaltyRate","type":"uint256"},{"internalType":"address","name":"royaltyAddress","type":"address"},{"internalType":"uint256","name":"feeRate","type":"uint256"},{"internalType":"address","name":"feeAddress","type":"address"}],"internalType":"struct SignTest.Fee","name":"fee","type":"tuple"}],"internalType":"struct SignTest.Detail","name":"detail","type":"tuple"}],"name":"detailEncode","outputs":[{"internalType":"bytes","name":"","type":"bytes"}],"stateMutability":"pure","type":"function"},{"inputs":[{"components":[{"components":[{"internalType":"address","name":"token","type":"address"},{"internalType":"uint256","name":"tokenId","type":"uint256"},{"internalType":"uint256","name":"amount","type":"uint256"},{"internalType":"uint8","name":"kind","type":"uint8"}],"internalType":"struct SignTest.TokenNFT[]","name":"bundle","type":"tuple[]"},{"internalType":"uint8","name":"op","type":"uint8"},{"internalType":"uint256","name":"orderId","type":"uint256"},{"internalType":"bytes32","name":"orderHash","type":"bytes32"},{"internalType":"address","name":"signer","type":"address"},{"internalType":"uint256","name":"txDeadline","type":"uint256"},{"internalType":"bytes32","name":"salt","type":"bytes32"},{"internalType":"address","name":"caller","type":"address"},{"internalType":"contract IERC20","name":"currency","type":"address"},{"internalType":"uint256","name":"price","type":"uint256"},{"components":[{"internalType":"uint256","name":"royaltyRate","type":"uint256"},{"internalType":"address","name":"royaltyAddress","type":"address"},{"internalType":"uint256","name":"feeRate","type":"uint256"},{"internalType":"address","name":"feeAddress","type":"address"}],"internalType":"struct SignTest.Fee","name":"fee","type":"tuple"}],"internalType":"struct SignTest.Detail","name":"detail","type":"tuple"}],"name":"detailHash","outputs":[{"internalType":"bytes32","name":"","type":"bytes32"}],"stateMutability":"pure","type":"function"},{"inputs":[{"components":[{"components":[{"internalType":"address","name":"token","type":"address"},{"internalType":"uint256","name":"tokenId","type":"uint256"},{"internalType":"uint256","name":"amount","type":"uint256"},{"internalType":"uint8","name":"kind","type":"uint8"}],"internalType":"struct SignTest.TokenNFT[]","name":"bundle","type":"tuple[]"},{"internalType":"uint8","name":"op","type":"uint8"},{"internalType":"uint256","name":"orderId","type":"uint256"},{"internalType":"bytes32","name":"orderHash","type":"bytes32"},{"internalType":"address","name":"signer","type":"address"},{"internalType":"uint256","name":"txDeadline","type":"uint256"},{"internalType":"bytes32","name":"salt","type":"bytes32"},{"internalType":"address","name":"caller","type":"address"},{"internalType":"contract IERC20","name":"currency","type":"address"},{"internalType":"uint256","name":"price","type":"uint256"},{"components":[{"internalType":"uint256","name":"royaltyRate","type":"uint256"},{"internalType":"address","name":"royaltyAddress","type":"address"},{"internalType":"uint256","name":"feeRate","type":"uint256"},{"internalType":"address","name":"feeAddress","type":"address"}],"internalType":"struct SignTest.Fee","name":"fee","type":"tuple"}],"internalType":"struct SignTest.Detail","name":"detail","type":"tuple"}],"name":"detailHashPrefix","outputs":[{"internalType":"bytes32","name":"","type":"bytes32"}],"stateMutability":"pure","type":"function"},{"inputs":[{"components":[{"components":[{"internalType":"address","name":"token","type":"address"},{"internalType":"uint256","name":"tokenId","type":"uint256"},{"internalType":"uint256","name":"amount","type":"uint256"},{"internalType":"uint8","name":"kind","type":"uint8"}],"internalType":"struct SignTest.TokenNFT[]","name":"bundle","type":"tuple[]"},{"internalType":"uint8","name":"op","type":"uint8"},{"internalType":"uint256","name":"orderId","type":"uint256"},{"internalType":"bytes32","name":"orderHash","type":"bytes32"},{"internalType":"address","name":"signer","type":"address"},{"internalType":"uint256","name":"txDeadline","type":"uint256"},{"internalType":"bytes32","name":"salt","type":"bytes32"},{"internalType":"address","name":"caller","type":"address"},{"internalType":"contract IERC20","name":"currency","type":"address"},{"internalType":"uint256","name":"price","type":"uint256"},{"components":[{"internalType":"uint256","name":"royaltyRate","type":"uint256"},{"internalType":"address","name":"royaltyAddress","type":"address"},{"internalType":"uint256","name":"feeRate","type":"uint256"},{"internalType":"address","name":"feeAddress","type":"address"}],"internalType":"struct SignTest.Fee","name":"fee","type":"tuple"}],"internalType":"struct SignTest.Detail","name":"detail","type":"tuple"},{"internalType":"bytes","name":"sigDetail","type":"bytes"}],"name":"detailSign","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"pure","type":"function"},{"inputs":[{"components":[{"components":[{"internalType":"address","name":"token","type":"address"},{"internalType":"uint256","name":"tokenId","type":"uint256"},{"internalType":"uint256","name":"amount","type":"uint256"},{"internalType":"uint8","name":"kind","type":"uint8"}],"internalType":"struct SignTest.TokenNFT[]","name":"bundle","type":"tuple[]"},{"internalType":"address","name":"user","type":"address"},{"internalType":"contract IERC20","name":"currency","type":"address"},{"internalType":"uint256","name":"price","type":"uint256"},{"internalType":"uint256","name":"deadline","type":"uint256"},{"internalType":"bytes32","name":"salt","type":"bytes32"},{"internalType":"uint8","name":"kind","type":"uint8"}],"internalType":"struct SignTest.Order","name":"order","type":"tuple"}],"name":"orderEncode","outputs":[{"internalType":"bytes","name":"","type":"bytes"}],"stateMutability":"pure","type":"function"},{"inputs":[{"components":[{"components":[{"internalType":"address","name":"token","type":"address"},{"internalType":"uint256","name":"tokenId","type":"uint256"},{"internalType":"uint256","name":"amount","type":"uint256"},{"internalType":"uint8","name":"kind","type":"uint8"}],"internalType":"struct SignTest.TokenNFT[]","name":"bundle","type":"tuple[]"},{"internalType":"address","name":"user","type":"address"},{"internalType":"contract IERC20","name":"currency","type":"address"},{"internalType":"uint256","name":"price","type":"uint256"},{"internalType":"uint256","name":"deadline","type":"uint256"},{"internalType":"bytes32","name":"salt","type":"bytes32"},{"internalType":"uint8","name":"kind","type":"uint8"}],"internalType":"struct SignTest.Order","name":"order","type":"tuple"}],"name":"orderHash","outputs":[{"internalType":"bytes32","name":"","type":"bytes32"}],"stateMutability":"pure","type":"function"},{"inputs":[{"components":[{"components":[{"internalType":"address","name":"token","type":"address"},{"internalType":"uint256","name":"tokenId","type":"uint256"},{"internalType":"uint256","name":"amount","type":"uint256"},{"internalType":"uint8","name":"kind","type":"uint8"}],"internalType":"struct SignTest.TokenNFT[]","name":"bundle","type":"tuple[]"},{"internalType":"address","name":"user","type":"address"},{"internalType":"contract IERC20","name":"currency","type":"address"},{"internalType":"uint256","name":"price","type":"uint256"},{"internalType":"uint256","name":"deadline","type":"uint256"},{"internalType":"bytes32","name":"salt","type":"bytes32"},{"internalType":"uint8","name":"kind","type":"uint8"}],"internalType":"struct SignTest.Order","name":"order","type":"tuple"}],"name":"orderHashPrefix","outputs":[{"internalType":"bytes32","name":"","type":"bytes32"}],"stateMutability":"pure","type":"function"},{"inputs":[{"components":[{"components":[{"internalType":"address","name":"token","type":"address"},{"internalType":"uint256","name":"tokenId","type":"uint256"},{"internalType":"uint256","name":"amount","type":"uint256"},{"internalType":"uint8","name":"kind","type":"uint8"}],"internalType":"struct SignTest.TokenNFT[]","name":"bundle","type":"tuple[]"},{"internalType":"address","name":"user","type":"address"},{"internalType":"contract IERC20","name":"currency","type":"address"},{"internalType":"uint256","name":"price","type":"uint256"},{"internalType":"uint256","name":"deadline","type":"uint256"},{"internalType":"bytes32","name":"salt","type":"bytes32"},{"internalType":"uint8","name":"kind","type":"uint8"}],"internalType":"struct SignTest.Order","name":"order","type":"tuple"},{"internalType":"bytes","name":"sigOrder","type":"bytes"}],"name":"orderSign","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"pure","type":"function"}]';
    const SIGNER = '0x94C8ceb9BBf85a6e33a79A79948A8DC05732d119';                   //合约签名约定密钥
    const  PRO_PRIVATE_KEY_SIGN = 'd51e70ba71639101a2bfd564f1c1438d22ae69c5898a311a32af9bdfb1057b68';
    const  PRO_PRIVATE_KEY = '95afea6cbf1918498af6299b6c2e7c70b6151c6c85f6c3bdc7bf6cdc6af6f053';
    const ABI_NEW = '[{"inputs":[{"internalType":"string","name":"name_","type":"string"},{"internalType":"string","name":"symbol_","type":"string"}],"stateMutability":"nonpayable","type":"constructor"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"owner","type":"address"},{"indexed":true,"internalType":"address","name":"approved","type":"address"},{"indexed":true,"internalType":"uint256","name":"tokenId","type":"uint256"}],"name":"Approval","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"owner","type":"address"},{"indexed":true,"internalType":"address","name":"operator","type":"address"},{"indexed":false,"internalType":"bool","name":"approved","type":"bool"}],"name":"ApprovalForAll","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"from","type":"address"},{"indexed":true,"internalType":"address","name":"to","type":"address"},{"indexed":true,"internalType":"uint256","name":"tokenId","type":"uint256"}],"name":"Transfer","type":"event"},{"inputs":[{"internalType":"address","name":"to","type":"address"},{"internalType":"uint256","name":"tokenId","type":"uint256"}],"name":"approve","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"owner","type":"address"}],"name":"balanceOf","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"tokenId","type":"uint256"}],"name":"getApproved","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"address","name":"owner","type":"address"},{"internalType":"address","name":"operator","type":"address"}],"name":"isApprovedForAll","outputs":[{"internalType":"bool","name":"","type":"bool"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"name","outputs":[{"internalType":"string","name":"","type":"string"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"tokenId","type":"uint256"}],"name":"ownerOf","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"address","name":"from","type":"address"},{"internalType":"address","name":"to","type":"address"},{"internalType":"uint256","name":"tokenId","type":"uint256"}],"name":"safeTransferFrom","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"from","type":"address"},{"internalType":"address","name":"to","type":"address"},{"internalType":"uint256","name":"tokenId","type":"uint256"},{"internalType":"bytes","name":"_data","type":"bytes"}],"name":"safeTransferFrom","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"operator","type":"address"},{"internalType":"bool","name":"approved","type":"bool"}],"name":"setApprovalForAll","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"bytes4","name":"interfaceId","type":"bytes4"}],"name":"supportsInterface","outputs":[{"internalType":"bool","name":"","type":"bool"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"symbol","outputs":[{"internalType":"string","name":"","type":"string"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"tokenId","type":"uint256"}],"name":"tokenURI","outputs":[{"internalType":"string","name":"","type":"string"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"address","name":"from","type":"address"},{"internalType":"address","name":"to","type":"address"},{"internalType":"uint256","name":"tokenId","type":"uint256"}],"name":"transferFrom","outputs":[],"stateMutability":"nonpayable","type":"function"}]';

    public function getBytes($string)
    {
        $bytes = array();
        for ($i = 0; $i < strlen($string); $i++) {
            $bytes[] = ord($string[$i]);
        }
        return $bytes;
    }

    public function toStr($bytes)
    {
        $str = '';
        foreach ($bytes as $ch) {
            $str .= chr($ch);
        }
        return $str;
    }

    /**
     * Notes: 签名
     * User: Deycecep
     * DateTime: 2022/4/22 16:45
     * @param $arr
     * @return string
     */
    public function sign($arr)
    {
        $signStr = '';
        $signStr .= str_pad($this->str_bcdechex($arr['op']), 64, '0', STR_PAD_LEFT);
        $signStr .= str_pad(substr($arr['orderHash'], 2), 64, '0', STR_PAD_LEFT);
        $signStr .= str_pad(substr($arr['signer'], 2), 64, '0', STR_PAD_LEFT);
        $signStr .= str_pad($this->str_bcdechex($arr['txDeadline']), 64, '0', STR_PAD_LEFT);
        $signStr .= str_pad(substr($arr['salt'], 2), 64, '0', STR_PAD_LEFT);
        $signStr .= str_pad(substr($arr['caller'], 2), 64, '0', STR_PAD_LEFT);
        $signStr .= str_pad($this->str_bcdechex($arr['price']), 64, '0', STR_PAD_LEFT);
        $signStr .= str_pad($this->str_bcdechex($arr['fee']['royaltyRate']), 64, '0', STR_PAD_LEFT);
        $signStr .= str_pad(substr($arr['fee']['royaltyAddress'], 2), 64, '0', STR_PAD_LEFT);
        $signStr .= str_pad($this->str_bcdechex($arr['fee']['feeRate']), 64, '0', STR_PAD_LEFT);
        $signStr .= str_pad(substr($arr['fee']['feeAddress'], 2), 64, '0', STR_PAD_LEFT);


//        var_dump('0x' . $signStr);
        $utils = (new Utils());
        $hexb = $utils::sha3('0x' . $signStr);
//        var_dump('第一次hash', $hexb);
        //去掉0x 再拼接固定值
        $removeHexb = substr($hexb, 2);
//        var_dump($removeHexb);
        $c = $removeHexb;

//        var_dump('拼接约定签名密钥', $c);
        $prefix = sprintf("\x19Ethereum Signed Message:\n%d%s", 32, hex2bin($c));
//        var_dump($prefix);
        $h2 = $utils::sha3($prefix);
//        var_dump('第二次hash', $h2);

//        var_dump('私钥', self::PRO_PRIVATE_KEY_SIGN);
        $res = $this->getRSV(self::PRO_PRIVATE_KEY_SIGN, $h2);

        $tem = '';
        if ($res['v'] == '36') {
            $tem = '1c';
        } elseif ($res['v'] == '35') {
            $tem = '1b';
        }
        $str = $res['r'] . $res['s'] . $tem;
        return $str;
    }

    public function str_bcdechex($dec)
    {
        $dec = (string)$dec;
        $last = bcmod($dec, 16);
        $remain = bcdiv(bcsub($dec, $last), 16);
        if ($remain == 0) {
            return dechex($last);
        } else {
            return $this->str_bcdechex($remain) . dechex($last);
        }
    }

    public function getRSV($prikey, $hash)
    {
        $prikey = $this->stripZero($prikey);
        $transaction = (new Util())->ecsign($prikey, $hash);
        $signature = json_decode(json_encode($transaction), true);
        $res['r'] = '0x' . $signature['r'];
        //$res['s'] = '0x'.$signature['s'];
        $res['s'] = $signature['s'];
        $res['v'] = $signature['recoveryParam'];
        return $res;
    }

    /**
     * stripZero
     *
     * @param string $value
     * @return string
     */
    public function stripZero(string $value)
    {
        if ($this->isZeroPrefixed($value)) {
            $count = 1;
            return str_replace('0x', '', $value, $count);
        }
        return $value;
    }

    /**
     * isZeroPrefixed
     *
     * @param string $value
     * @return bool
     */
    public function isZeroPrefixed(string $value)
    {
        return (strpos($value, '0x') === 0);
    }

    /**
     * 根据ABI格式encode
     * @param string $type
     * @param $value
     * @return string
     * @throws ContractABIException
     */
    protected function encodeArg(string $type, $value): string
    {
        $len = preg_replace('/[^0-9]/', '', $type);
        if (!$len) {
            $len = null;
        }

        $type = preg_replace('/[^a-z]/', '', $type);
        switch ($type) {
            case "hash":
            case "address":
                if (substr($value, 0, 2) === "0x") {
                    $value = substr($value, 2);
                }
                break;
            case "uint":
            case "int":
                $value = Integers::Pack_UInt_BE($value);
                break;
            case "bool":
                $value = $value === true ? 1 : 0;
                break;
            case "string":
//                $value = ASCII::base16Encode($value)->hexits(false);
                break;
            default:
                throw new ContractABIException(sprintf('Cannot encode value of type "%s"', $type));
        }

        return substr(str_pad(strval($value), 64, "0", STR_PAD_LEFT), 0, 64);
    }


    protected function _ethToWei($value, $decimals = 18)
    {

        return bcmul($value, bcpow('10', $decimals, 18));
    }

    public function apiContractUrl($contract, $abi = '', $method = '', $params = [], $key = self::PRO_PRIVATE_KEY)
    {
        $credential = Credential::fromKey($key);
        $client = NodeClient::create('testNet');//testNet //mainNet
//        $client = NodeClient::create('mainNet');//testNet //mainNet
        $customContract = new CustomContract($client, $credential, $contract, $abi);  //创建合约对象
        $result = $customContract->$method(...$params);
        return $result . '';
    }


    function getRandChar($length)
    {
        $str = null;
        $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($strPol) - 1;
        for ($i = 0; $i < $length; $i++) {
            $str .= $strPol[rand(0, $max)];
        }
        return $str;
    }

}