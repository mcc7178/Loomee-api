<?php
declare(strict_types = 1);

namespace App\Task\Laboratory;

use App\Foundation\Facades\Log;
use Hyperf\DbConnection\Db;


/**
 * 
 */
class CollectionTradeTotalTask
{
    
    /**
     * 组消息发送
     */
    public function execute()
    {
        $yestoday = date('Y-m-d',strtotime("-1 day"));
        $where_time = [$yestoday.' 00:00:00',$yestoday.' 23:59:59'];

        $list = Db::table('order')
        ->leftJoin('product', 'order.product_id', '=', 'product.id')
        ->select('order.*', 'product.collection_id','product.owner_id')
        ->where('order.status',1)
        ->whereBetween('order.updated_at',$where_time)
        ->get()->toArray();

        $coll = [];
        foreach($list as $k=>$v)
        {
            if(empty($v->collection_id))   continue;

            if(!array_key_exists($v->collection_id,$coll))
            {
                $coll[$v->collection_id] = [
                    'achievement' => $v->price * 1000,
                    'sales_nums' => 1,
                    'min_price' => $v->price
                ];

                if($v->owner_id != 0) $coll[$v->collection_id]['owners'][] = $v->owner_id;
            }
            else
            {
                $coll[$v->collection_id]['achievement'] += $v->price * 1000;
                $coll[$v->collection_id]['sales_nums'] += 1;
                if($v->price < $coll[$v->collection_id]['min_price'])    $coll[$v->collection_id]['min_price'] = $v->price;

                if($v->owner_id != 0) $coll[$v->collection_id]['owners'][] = $v->owner_id;
            }
        }

        $date = date('Y-m-d');
        $res = [];
        foreach($coll as $ka=>$va)
        {
            $data = [
                'collection_id' => $ka,
                'achievement' => $va['achievement']/1000,
                'sales_nums' => $va['sales_nums'],
                'avg_price' => round($va['achievement']/$va['sales_nums']/1000,3),
                'min_price' => $va['min_price'],
                'owners' => count(array_unique($va['owners'])),
                'date' => $date,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if(Db::table('collection_trade_total')->where(['collection_id'=>$ka,'date'=>$date])->doesntExist())   
            {
                Db::table('collection_trade_total')->insert($data);
                $res[] = $data;
            }
        }

        Log::debugLog()->debug('记录数据:'.json_encode($res));        
    }
}
