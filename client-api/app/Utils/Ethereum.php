<?php

namespace App\Utils;

use App\Exception\MsgException;
use BitWasp\Buffertools\Buffertools;
use Ethereum\Crypto\Signature;
use Ethereum\Types\Byte;
use Ethereum\Utils;

class Ethereum
{

    /**
     * @param string $hash  待签名散列，通过hashMessage方法对源数据进行hash
     * @param string $signature 通过私钥对待签名散列进行签名结果
     * @return string   公钥
     * @throws \Exception
     */
    static public function ecRecover(string $hash, string $signature) : string
    {
        if (!self::isHexStrict($hash)){
            throw new MsgException(Lang('undefinedHEX'), E_USER_WARNING);
//            trigger_error('不能明确的HEX', E_USER_WARNING);
//            $hash = '0x'.$hash;
        }
        if (!self::isHexStrict($signature)){
            throw new MsgException(Lang('undefinedHEX'), E_USER_WARNING);
//            trigger_error('不能明确的HEX', E_USER_WARNING);
//            $signature = '0x'.$signature;
        }
        $pubKey = self::recoverPublicKey($hash, $signature);
        return $pubKey->getHex();
    }

    /**
     * 要传递给ecRecover的散列，数据将按以下方式进行UTF-8十六进制解码和封装并使用keccak256进行hash
     * "\x19Ethereum Signed Message:\n" + message.length + message
     * @param string $message
     * @return string
     * @throws \Exception
     */
    static public function hashMessage(string $message) : string
    {
        if (!static::isHexStrict($message)){
            $message = static::utf8ToHex($message);
        }
        $messageBuffer = static::hex2Bytes($message);
        $preamble = sprintf("\x19Ethereum Signed Message:\n%d", $messageBuffer->getSize());
        $preambleBuffer = Byte::init($preamble);
        $ethMessage = Buffertools::concat($preambleBuffer->getBuffer(), $messageBuffer->getBuffer());
        $hash = static::sha3($ethMessage->getBinary());
        return '0x'.$hash;
    }

    /**
     * 公钥转换eth地址，小写，不做checksum
     * @param string $publicKey
     * @return string
     * @throws \Exception
     */
    static public function publicKey2Address(string $publicKey) : string
    {
        return strtolower(
            Utils::ensureHexPrefix(
                Byte::initWithHex(
                    static::sha3(
                        Byte::initWithHex($publicKey)->slice(1)->getBinary()
                    )
                )->slice(12)->getBuffer()->getHex()
            )
        );
    }

    /**
     * hex转字节
     * @param string $hex
     * @return Byte
     * @throws \Exception
     */
    static private function hex2Bytes(string $hex)
    {
        //低位前补零
        $len = strlen($hex);
        if ($len % 2 !== 0){
            $index = $len - 1;
            $hex[$index] = '0'.$hex[$index];
        };
        return Byte::initWithHex($hex);
    }

    /**
     * @param string $string
     * @return string
     */
    static private function utf8ToHex(string $string) : string
    {
        //    $string = mb_convert_encoding($string, 'UTF-8');
        $string = utf8_encode($string);
        $hex = '0x';
        // remove \u0000 padding from either side
//        $string =  preg_replace('/^(?:\u0000)*/', '', $string);
//        $string = implode(array_reverse(str_split($string)), '');
//        $string =  preg_replace('/^(?:\u0000)*/', '', $string);
//        $string = implode(array_reverse(str_split($string)), '');

        $len = mb_strlen($string);
        for ($i = 0; $i < $len; $i++){
            $char = mb_substr($string, $i, 1);
            $ord = mb_ord($char);
//        if ($ord !== 0){
            $ordHex = dechex($ord);
            $hex .= str_pad($ordHex, 2, '0', STR_PAD_LEFT);
//        }
        }
        return $hex;
    }

    /**
     * 哈希算法
     * @param string $waitHashString
     * @return string
     */
    static private function sha3(string $waitHashString) : string
    {
        return keccak_hash($waitHashString, 256, false);
    }

    /**
     * 判断是否十六进制串
     * @param string $hex
     * @return bool
     */
    static private function isHexStrict(string $hex) :bool
    {
        return (is_string($hex) || is_numeric($hex)) && (preg_match('@^-?0x[0-9a-f]*$@i', $hex) > 0);
    }


    /**
     * ECDSA验证签名
     * @param string $hash
     * @param string $signature
     * @return Byte
     * @throws \Exception
     */
    static private function recoverPublicKey(string $hash, string $signature) : Byte
    {
        $hash = Byte::initWithHex($hash);
        $signature = Byte::initWithHex($signature);

        /** @var resource $context */
        $context = secp256k1_context_create(SECP256K1_CONTEXT_SIGN | SECP256K1_CONTEXT_VERIFY);
        /** @var resource $secpSignature */
        $secpSignature = null;
        $recoveryId = $signature->slice(64)->getInt();
        $recoveryId -= 0x1b;
        secp256k1_ecdsa_recoverable_signature_parse_compact($context, $secpSignature, $signature->slice(0, 64)->getBinary(), $recoveryId);
        /** @var resource $secpPublicKey */
        $secpPublicKey = null;
        secp256k1_ecdsa_recover($context, $secpPublicKey, $secpSignature, $hash->getBinary());
        $publicKey = '';
        secp256k1_ec_pubkey_serialize($context, $publicKey, $secpPublicKey, 2);
        unset($context, $secpSignature, $secpPublicKey);
        return Byte::init($publicKey);
    }
}