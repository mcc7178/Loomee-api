<?php

namespace App\Task;

use App\Lib\BSC\BSCCheckWithdrawHash;
use App\Lib\ERC20TransferLog;
use App\Log;
use App\Model\BlockLog;
use App\Model\Product\Order;
use App\Model\Product\Product;
use App\Model\User;
use Hyperf\DbConnection\Db;



class BlockLogCheckTask
{


    public function execute()
    {
        try {
            BlockLog::query()->where(['checkHash'=>0])->where('checkNum','<',30)->chunkById(100,function($data){
                foreach ($data as $v) {
                    $result = (new ERC20TransferLog())->checkHash($v->hash);
                    if(!empty($result)){
                        var_dump('zzzz66666'.$v->hash);
                        $v->checkHash = 1;
                        $res = $v->save();var_dump($res);

                    if($v->symbol == 'NFT'){
                        $order = Order::getOneById($v->order_id);
                        $product = Product::getOneByWhere(['id' => $v->product_id]);
                        if($v->type == '1'){//购买
                            if(empty($order->hash))
                                (new ERC20TransferLog())->orderCallback($product,$v->from,$v->to,$v->hash,$v->order_id,$v->scan_time);
                        }elseif($v->type == '6'){//下架
                            if($product->status == 1)
                                (new ERC20TransferLog())->cancelProduct($product,$v->from,$v->to,$v->hash,$v->scan_time);
                        }
                    }elseif($v->symbol == 'TRANSFER'){
                        $product = Product::getOneByWhere(['id' => $v->product_id]);
                        if($product)
                            (new ERC20TransferLog())->transferProduct($product,$v->from,$v->to,$v->hash,$v->scan_time);
                    }

                    }else{
                        var_dump('到这里了'.$v->hash);
                        $v->checkNum = Db::raw('checkNum+1');
                        $v->save();
                    }
                }
            });
        } catch (\Exception $e) {
            Log::get()->error('BlockLog', [$e->getMessage() . '---' . $e->getCode()]);
        }

    }

    protected function decodeAbiAddress(string $address): string
    {
        return strtolower($this->with0x(substr($this->without0x($address), 24)));
    }


    protected function with0x(string $address): string
    {
        if (preg_match('@^0x@i', $address)) {
            return $address;
        }
        return '0x' . $address;

    }

    protected function without0x(string $address): string
    {
        if (preg_match('@^0x@i', $address)) {
            $address = substr($address, 2);
        }
        return $address;
    }

    protected function hex2dec($hex): string
    {
        $hex = (string)$this->without0x(strval($hex));
        if (strlen($hex) === 1) {
            return strval(hexdec($hex));
        } else {
            $remain = substr($hex, 0, -1);
            $last = substr($hex, -1);
            return bcadd(bcmul('16', $this->hex2dec($remain), 0), $this->hex2dec($last), 0);
        }
    }


}
