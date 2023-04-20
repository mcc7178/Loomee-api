<?php
namespace App\Utils;

use App\Logic\ContractLogic;
use Elliptic\EC;
use FurqanSiddiqui\Ethereum\Exception\ContractABIException;
use FurqanSiddiqui\Ethereum\Math\Integers;
use Web3\Utils;
use Web3\Web3;
use Web3p\EthereumUtil\Util;


class ContractSign
{
//    const PRIVATE_KET_TEST = '108e8c584c8771fcbbfc19e59f807c89202e12b916c3addc1b5fd49159c68406';         //钱包私钥
    const PRIVATE_STR = 'f91d4398c273ab605caf6433c9489ff7c0f040291c5504f0142f886a696eafed';                   //合约签名约定密钥

    public function sign($arr)
    {
        $signStr = '';
        foreach ($arr as $k=>$item) {
            if (in_array($k, ['orderId', 'amount', 'deadline'])) {
                $resStr = $this->str_bcdechex($item['value']);
                $signStr .= str_pad($resStr, 64, '0', STR_PAD_LEFT);
            } else {
                $signStr .= $this->encodeArg($item['type'], strtolower($item['value']));
            }
        }
        var_dump($signStr);
        $utils = (new Utils());
        $hexb = $utils::sha3('0x'.$signStr);
        var_dump('第一次hash',$hexb);
        //去掉0x 再拼接固定值
        $removeHexb = substr($hexb, 2);
        $c = self::PRIVATE_STR.$removeHexb;
        var_dump('拼接约定签名密钥',$c);
//        var_dump('私钥',self::PRIVATE_KET_TEST);
        $h2 = $utils::sha3($c);
        var_dump('第二次hash',$h2);

//        var_dump('私钥',ContractApiLogic::PRO_PRIVATE_KEY_SIGN);
        $res = $this->getRSV(ContractLogic::PRO_PRIVATE_KEY_SIGN,$h2);
//        $res = $this->getRSV('cce6900962a60d7e8ce25810638f05a41cfc5ba12314aa9cc45736dd705f4401',$h2);
        var_dump($res);
        return ['v'=>$res['v'],'r'=>$res['r'],'s'=>$res['s']];
    }


    protected function str_bcdechex($dec) {
        $dec = (string)$dec;
        $last = bcmod($dec, 16);
        $remain = bcdiv(bcsub($dec, $last), 16);
        if($remain == 0) {
            return dechex($last);
            } else {
            return $this->str_bcdechex($remain).dechex($last);
        }
    }

    public function getRSV($prikey, $hash)
    {
//        $ec = new EC('secp256k1');
//        $chainId = 0;
//        // Generate keys
//        $prikey = $this->stripZero($prikey);
//        $key = $ec->keyFromPrivate($prikey);
//        $signature = $key->sign($hash, ['canonical' => true]);
//        $transaction['r'] = '0x'.$signature->r->toString('hex');
//        $transaction['s'] = '0x'.$signature->s->toString('hex');
//        $transaction['v'] = dechex($signature->recoveryParam + 35);
        $transaction = (new Util())->ecsign($prikey,$hash);
        $signature = json_decode(json_encode($transaction),true);
        $res['r'] = '0x'.$signature['r'];
        $res['s'] = '0x'.$signature['s'];
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


    protected function _ethToWei($value, $decimals){

        return bcmul($value, bcpow('10', $decimals, 18));
    }

}