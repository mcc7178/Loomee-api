<?php

namespace App\Task;

use App\Foundation\Facades\Log;
use App\Foundation\Utils\Mail;
use App\Pool\Redis;
use App\Utils\Bsc\Credential;
use App\Utils\Bsc\CustomContract;
use App\Utils\Bsc\Kit;
use App\Utils\Bsc\NodeClient;
use App\Utils\Bsc\NodeClientBsc;

class BalanceMonitorTask
{
    private $addressArr = [
        '0x9f92dc568255765ef91f9ba757868548bcc36e4a',
        '0x019f7fefd9bb995027d05674d93d16c5dabca2c9',
        '0x682df69cadf8d3ed2c568b73701344ffc5bd4c2f',
        '0x57b727991c54b855a432df686287906f0935d6ce',
        '0x2ac6d87b26c430bb1e9d51885242719e70e21634',
        '0xf29067d0F37675Ef676857a66e91C1272Ef242eF',
        '0x05f0fDD0E49A5225011fff92aD85cC68e1D1F08e',
    ];

    private $contract = '0x55d398326f99059ff775485246999027b3197955';
    private $abi = '[{"inputs":[],"payable":false,"stateMutability":"nonpayable","type":"constructor"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"owner","type":"address"},{"indexed":true,"internalType":"address","name":"spender","type":"address"},{"indexed":false,"internalType":"uint256","name":"value","type":"uint256"}],"name":"Approval","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"previousOwner","type":"address"},{"indexed":true,"internalType":"address","name":"newOwner","type":"address"}],"name":"OwnershipTransferred","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"from","type":"address"},{"indexed":true,"internalType":"address","name":"to","type":"address"},{"indexed":false,"internalType":"uint256","name":"value","type":"uint256"}],"name":"Transfer","type":"event"},{"constant":true,"inputs":[],"name":"_decimals","outputs":[{"internalType":"uint8","name":"","type":"uint8"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"_name","outputs":[{"internalType":"string","name":"","type":"string"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"_symbol","outputs":[{"internalType":"string","name":"","type":"string"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[{"internalType":"address","name":"owner","type":"address"},{"internalType":"address","name":"spender","type":"address"}],"name":"allowance","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"internalType":"address","name":"spender","type":"address"},{"internalType":"uint256","name":"amount","type":"uint256"}],"name":"approve","outputs":[{"internalType":"bool","name":"","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[{"internalType":"address","name":"account","type":"address"}],"name":"balanceOf","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"internalType":"uint256","name":"amount","type":"uint256"}],"name":"burn","outputs":[{"internalType":"bool","name":"","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[],"name":"decimals","outputs":[{"internalType":"uint8","name":"","type":"uint8"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"internalType":"address","name":"spender","type":"address"},{"internalType":"uint256","name":"subtractedValue","type":"uint256"}],"name":"decreaseAllowance","outputs":[{"internalType":"bool","name":"","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[],"name":"getOwner","outputs":[{"internalType":"address","name":"","type":"address"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"internalType":"address","name":"spender","type":"address"},{"internalType":"uint256","name":"addedValue","type":"uint256"}],"name":"increaseAllowance","outputs":[{"internalType":"bool","name":"","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":false,"inputs":[{"internalType":"uint256","name":"amount","type":"uint256"}],"name":"mint","outputs":[{"internalType":"bool","name":"","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[],"name":"name","outputs":[{"internalType":"string","name":"","type":"string"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"owner","outputs":[{"internalType":"address","name":"","type":"address"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[],"name":"renounceOwnership","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[],"name":"symbol","outputs":[{"internalType":"string","name":"","type":"string"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"totalSupply","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"internalType":"address","name":"recipient","type":"address"},{"internalType":"uint256","name":"amount","type":"uint256"}],"name":"transfer","outputs":[{"internalType":"bool","name":"","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":false,"inputs":[{"internalType":"address","name":"sender","type":"address"},{"internalType":"address","name":"recipient","type":"address"},{"internalType":"uint256","name":"amount","type":"uint256"}],"name":"transferFrom","outputs":[{"internalType":"bool","name":"","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":false,"inputs":[{"internalType":"address","name":"newOwner","type":"address"}],"name":"transferOwnership","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"}]';
    private $method = 'balanceOf';
    const  PRO_PRIVATE_KEY = '95afea6cbf1918498af6299b6c2e7c70b6151c6c85f6c3bdc7bf6cdc6af6f053';

    public function exec()
    {
        $str = '';
        $send = false;
        $redis = Redis::getInstance();
        foreach ($this->addressArr as $address) {
            $usdtBalance = $this->apiContractUrl($this->contract, $this->abi, $this->method, [$address]);
            $usdtBalance = $usdtBalance ? bcdiv((string)$usdtBalance, '1000000000000000000', 8) : 0;
            $usdtKey = $address . '_usdt_balance:';

            $client = new NodeClient('https://bsc-dataseed.binance.org/');;
            $credential = Credential::fromKey(self::PRO_PRIVATE_KEY);
            $bnbBalance = (new Kit($client, $credential))->balanceOf($address);
            $bnbBalance = $bnbBalance ? bcdiv((string)$bnbBalance, '1000000000000000000', 8) : 0;
            $bnbKey = $address . '_bnb_balance:';

            $usdt = $bnb = 0;
            if (!$redis->hExists('balanceMonitor', $usdtKey) || !$redis->hExists('balanceMonitor', $bnbKey)) {
                $redis->hSet('balanceMonitor', $usdtKey, $usdtBalance);
                $redis->hSet('balanceMonitor', $bnbKey, $bnbBalance);
            } else {
                $usdt = $redis->hGet('balanceMonitor', $usdtKey) ?? 0;
                $bnb = $redis->hGet('balanceMonitor', $bnbBalance) ?? 0;
                if ($usdt != $usdtBalance || $bnb != $bnbBalance) {
                    $send = true;
                }
            }
            $str .= "<p>$address 原usdt余额:<strong>$usdt</strong>,现usdt余额:<strong>$usdtBalance</strong>;
原bnb余额:<strong>$bnb</strong>,现bnb余额:<strong>$bnbBalance</strong></p>";
        }
        if ($send) {
            Log::codeDebug()->info("sendEmail:" . $str);
            $this->sendEmail($str);
        }
    }

    public function apiContractUrl($contract, $abi = '', $method = '', $params = [], $key = self::PRO_PRIVATE_KEY)
    {
        $credential = Credential::fromKey($key);
        $client = NodeClientBsc::create('mainNet');
        $customContract = new CustomContract($client, $credential, $contract, $abi);  //创建合约对象
        $result = $customContract->$method(...$params);
        return $result . '';
    }

    private function sendEmail($body)
    {
        $config = [
            'username' => 'notifynotify@tradingking.vip',
            'password' => 'Abc123456.',
            'host' => 'smtpout.secureserver.net'
        ];
        Mail::init($config)->setFromAddress($config['username'], 'Tradingking')
            ->setAddress('289569555@qq.com', '')
            ->setSubject('余额变动')
            ->setBody($this->getEmailHtml($body))
            ->send();
    }

    /**
     * 获取Email模板
     * @param string $code
     * @return string
     */
    private function getEmailHtml(string $code): string
    {
        return '<body style="color: #666; font-size: 14px; font-family: \'Open Sans\',Helvetica,Arial,sans-serif;">
                    <div class="box-content" style="width: 80%; margin: 20px auto; max-width: 1200px; min-width: 600px;">
                        <div class="info-wrap" style="border-bottom-left-radius: 10px;border-bottom-right-radius: 10px;
                                                  border:1px solid #ddd;
                                                  overflow: hidden;
                                                  padding: 15px 15px 20px;">
                            <div class="tips" style="padding:15px;">
                                <p style=" list-style: 160%; margin: 10px 0;">' . $code . '</p>
                            </div>
                            <table class="list" style="width: 100%; border-collapse: collapse; border-top:1px solid #eee; font-size:12px;">
                            </table>
                        </div>
                    </div>
                </body>';
    }
}