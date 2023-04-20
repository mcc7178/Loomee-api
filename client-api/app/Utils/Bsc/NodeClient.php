<?php
namespace App\Utils\Bsc;

use Exception;
use Web3\Web3;
use Web3\Utils;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;

class NodeClient extends Web3{
  function __construct($url){
    $provider = new HttpProvider(
        new HttpRequestManager($url, 300) //timeout
    );
    parent::__construct($provider);
  }

  static function create($network){
    if($network === 'mainNet') return self::mainNet();
    if($network === 'testNet') return self::testNet();
    throw new Exception('unsupported network');
  }
  static function testNet(){
    return new self('https://rinkeby.infura.io/v3/1a5167a478f748ea9e675bcb1e35fb25');
  }

  static function mainNet(){
    return new self('https://mainnet.infura.io/v3/1a5167a478f748ea9e675bcb1e35fb25');
  }

  function getBalance($addr){
    $cb = new Callback;
    $this->getEth()->getBalance($addr, $cb);
    return $cb->result;
  }

  function broadcast($rawtx){
    $cb = new Callback;
    $this->getEth()->sendRawTransaction($rawtx, $cb);
    return $cb->result;
  }

  function getReceipt($txid){
    $cb = new Callback;
    $this->getEth()->getTransactionReceipt($txid, $cb);
    return $cb->result;
  }

  function waitForConfirmation($txid, $timeout = 300){
    $expire = time() + $timeout;
    while(time() < $expire) {
      try{
        $receipt = $this->getReceipt($txid);
        if(!is_null($receipt)) return $receipt;
        sleep(2);
      }catch(Exception $e){}
    }
    throw new Exception('tx not confirmed yet.');
  }
}
