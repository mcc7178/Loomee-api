<?php

declare(strict_types=1);

namespace App\Command;

use App\Lib\ERC20TransferLog;
use App\Model\BlockLog;
use App\Model\Product\Order;
use App\Model\Product\Product;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Psr\Container\ContainerInterface;
use Hyperf\DbConnection\Db;

/**
 * @Command
 */
class TestCommad extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct('xxx');
    }

    public function configure()
    {
    }

    public function handle()
    {
        $fromBlockDec = 10685320;
        (new \App\Lib\ERC20TransferLog())->getEthTransactionsLog($fromBlockDec);


//            BlockLog::query()->where(['checkHash'=>0])->where('checkNum','<',30)->chunkById(100,function($data){
//                foreach ($data as $v) {
//                    $result = (new ERC20TransferLog())->checkHash($v->hash);
//                    var_dump($result);
//                    $flag = isset($result['result']) && $result['result']['status']!='0x0';
//                    if(!$flag){
//                        var_dump('zzzz66666'.$v->hash);
//                        $v->checkHash = 1;
//                        $v->save();
//
//                        if($v->symbol == 'NFT'){
//                            $order = Order::getOneById($v->order_id);
//                            $product = Product::getOneByWhere(['id' => $v->product_id]);
//                            if($v->op == '1'){//购买
//                                if(empty($order->hash))
//                                    (new ERC20TransferLog())->orderCallback($product,$v->from,$v->to,$v->hash,$v->order_id);
//                            }elseif($v->op == '6'){//下架
//                                if($product->status == 1)
//                                    (new ERC20TransferLog())->cancelProduct($product,$v->from,$v->to,$v->hash);
//                            }
//                        }elseif($v->symbol == 'TRANSFER'){
//                            $product = Product::getOneByWhere(['id' => $v->product_id]);
//                            if($product)
//                                (new ERC20TransferLog())->transferProduct($product,$v->from,$v->to,$v->hash);
//                        }
//
//                    }else{
//                        var_dump('到这里了'.$v->hash);
//                        $v->checkNum = Db::raw('checkNum+1');
//                        $v->save();
//                    }
//                }
//            });
//

    }
}
