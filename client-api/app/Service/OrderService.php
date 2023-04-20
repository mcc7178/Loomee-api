<?php

namespace App\Services;

use App\Model\MarketOperation;
use App\Model\MarketOperationUser;
use App\Model\Markets;
use App\Lib\Redis;
use App\Exceptions\BaseException;
use App\Model\Trade;
use App\Model\User;
use App\Model\UserAssetDeals;
use App\Model\FinanceLog;
use App\Model\OpenapiOrder;
use App\Lib\Push as PushLib;
use App\Lib\Formatter;
use App\Lib\Common;
use App\Model\UserAssetRecharges;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService extends BaseService
{

    /*
    * 下单
    */
    public static function order($params, $userid)
    {
        $marketName = strtolower($params['market']);

        $market = Markets::where('name', $marketName)->first();


        if (!$market || $market->is_enabled == 0) {
            throw new BaseException('withdraw.Trading pairs do not exist or suspend trading');
        }

        $op_user = false;
        $op = MarketOperation::query()->where('market', $marketName)->where('status', 1)->first();
        if ($op) {
            $op_user = MarketOperationUser::query()->where('userid', $userid)->where('op_id', $op->id)->first();
        }

        if ($market->is_enabled == 2 && !$op_user) {
            throw new BaseException('wallet.Not open yet');
        }

        //默认精度
        bcscale(8);
        $amount = $params['quantity'];//交易额

        // 判断时间
        // 判断开始时间
        $dayStartTime = strtotime(date("Y-m-d"));
        $time = time() - $dayStartTime;

        $amStartTime = $market->start_time;
        $amStopTime = $market->end_time;

        $pmStartTime = $market->pm_start_time;
        $pmStopTime = $market->pm_end_time;

        if (!$op_user) {

            if ($amStartTime && $amStopTime && $pmStartTime && $pmStopTime) {
                if ($amStartTime > $time || ($amStopTime < $time && $pmStartTime > $time) || $time > $pmStopTime) {
                    throw new BaseException('wallet.Out of market');
                }
            } else if ($amStartTime && $amStopTime) {
                if ($amStartTime > $time || $amStopTime < $time)
                    throw new BaseException('wallet.Out of market');
            } else if ($pmStartTime && $pmStopTime) {
                if ($pmStartTime > $time || $pmStopTime < $time)
                    throw new BaseException('wallet.Out of market');
            }
        }


        $user = User::query()->find($userid);
        if ($user->status == 0 || $user->is_disabled_trade == 1) {
            throw new BaseException('wallet.Delegation failed');
        }

        //ume 涨跌限制
        if ($market->trade_limit && $market->trade_limit > 0 && $params['trade_type'] == 'limit') {

            $holiday = self::isHoliday();
            if ($holiday)
                throw new BaseException("{$holiday['name']}".trans('wallet.Out of market'));;

            if ((date('w', time()) == 6) || (date('w', time()) == 0)) {
                throw new BaseException('wallet.Out of market');
            }

            $openPrice = $market->trade_limit;
            $marketKay = $marketName . '-everyday-one-price';
            $redisData = Redis::getInstance(4)->hGet($marketKay, date("Y-m-d"));

            if ($redisData) {
                $p = bcmul($redisData, $openPrice, 8);
                $buyPrice = bcadd($p, $redisData, 8);
                $sellPrice = bcsub($redisData, $p, 8);
                if ($params['price'] > $buyPrice || $params['price'] < $sellPrice) {
                    throw new BaseException('wallet.The price is out of the limit');
                }
            }
        }

        if ($params['trade_type'] == 'limit') {
            if (bccomp($market->min_number, $params['quantity'], $market->decimals_number) === 1) {
                throw new BaseException('wallet.The minimum number of registered orders is|' . preg_replace('/(\.\d+?)(0+)$/', '$1', $market->min_number));
            }

            if ($market->max_number > 0 && bccomp($market->max_number, $params['quantity'], $market->decimals_number) === -1) {
                throw new BaseException('wallet.The maximum number of registered orders is|' . preg_replace('/(\.\d+?)(0+)$/', '$1', $market->max_number));
            }

            $amount = bcmul($params['quantity'], $params['price']);
        } elseif ($params['trade_type'] == 'market') {

            $marketOrders = json_decode(Redis::getInstance()->hget('entrust', $marketName), true);
            if (!$marketOrders || !$marketOrders[['buy' => 'sell', 'sell' => 'buy'][$params['type']]]) {
                throw new BaseException('wallet.Delegation failed');
            }
            $amount = $params['quantity'];
            $params['price'] = '0';
        }

        if ($market->min_amount > 0 && bccomp($market->min_amount, $amount) === 1) {
            throw new BaseException('wallet.The minimum amount of a single transaction is|' . preg_replace('/(\.\d+?)(0+)$/', '$1', $market->min_amount));
        }

        if (bccomp($amount, '0', $market->decimals_number) !== 1) {
            throw new BaseException('wallet.Wrong price or quantity');
        }

        if ($market->max_amount > 0 && bccomp($market->max_amount, $amount) === -1) {
            throw new BaseException('wallet.The maximum amount of a single transaction is|' . preg_replace('/(\.\d+?)(0+)$/', '$1', $market->max_amount));
        }

//        if ($params['type'] == 'sell') {
//            $lockOrders = (new Trade)->setTable(str_replace('/', '_', $marketName))->where(array('is_mining_lock' => 1, 'user_id' => $userid))->first();
//            if ($lockOrders) {
//                throw new BaseException("您还有未完成的共识委托订单，挂单失败");
//            }
//        }

        \DB::beginTransaction();
        try {

            $id = (new Trade)->setTable(str_replace('/', '_', $marketName))->insertGetId(
                array(
                    'user_id' => $userid,
                    'type' => $params['type'],
                    'trade_type' => $params['trade_type'],
                    'unsettled' => $params['quantity'],
                    'created_time' => time(),
                    'market' => $marketName,
                    'price' => $params['price'] ?: 0,
                    'quantity' => $params['quantity'],
                    'volume' => 0,
                )
            );

            //资产
            $coin = $params['type'] == 'buy' ? $market['to_coin'] : $market['from_coin'];

            $asset = UserAssetRecharges::where('userid', $userid)->where('coin', $coin)->lock()->first();


            $freezeAmount = $params['type'] == 'buy' ? $amount : $params['quantity'];

            if (!$asset || bccomp($asset->quantity, $freezeAmount) === -1) {
                throw new BaseException("wallet.Insufficient balance|".$coin);
            }

            $newQuantity = bcsub($asset->quantity, $freezeAmount);
            $newFreeze = bcadd($asset->freeze, $freezeAmount);


            FinanceLog::insert(array(
                'userid' => $userid,
                'coin' => $coin,
                'old_quantity' => $asset->quantity,
                'old_freeze' => $asset->freeze,
                'new_quantity' => $newQuantity,
                'new_freeze' => $newFreeze,
                'quantity' => '-' . $freezeAmount,
                'freeze' => $freezeAmount,
                'behavior' => 'trade',
                'behavior_id' => $id,
                'created_at' => time(),
                'account' => 'trade',
            ));


            UserAssetRecharges::where('userid', $userid)
                ->where('coin', $coin)
                ->update(array(
                    'quantity' => $newQuantity,
                    'freeze' => $newFreeze,
                ));

            //用户自编订单号
            if (!empty($params['client_order_id'])) {
                try {
                    OpenapiOrder::insert(array(
                        'trade_id' => $id,
                        'market' => $marketName,
                        'client_order_id' => $params['client_order_id'],
                        'userid' => $userid,
                        'ip' => (int)ip2long(Common::getRealIp()),
                    ));
                } catch (\PDOException $e) {
                    if (preg_match('/Duplicate entry \'(.+?)\' for key/', $e->getMessage(), $match)) {
                        throw new BaseException('wallet.Duplicate number|' . $params['client_order_id']);
                    }
                }

            }

            \DB::commit();

            $purchase_msg = trans('withdraw.Purchase'); //买入
            $sell_out_msg = trans('withdraw.Sell out'); //卖出

            //push
            $amount = $params['trade_type'] == 'limit' ? $params['price'] * $params['quantity'] : $params['quantity'];
            $msgData = array(
                'id' => $id,
                'volume' => '0',
                'status_txt' => '交易中',
                'status' => 'deal',
                'quantity' => Formatter::floor($params['quantity'], $market->decimals_number),
                'price' => $params['price'] ?: 0,
                'created_time' => time(),
                'market' => $marketName,
                'trade_type' => $params['trade_type'],
                'unsettled' => Formatter::floor($params['quantity'], $market->decimals_number),
                'type' => $params['type'] == 'buy' ? $purchase_msg : $sell_out_msg,
                'amount' => sprintf('%.' . $market->decimals_number . 'f', $amount),

            );
            // PushLib::sendToChannelUser($marketName, $userid, array('data' => $msgData, 'type' => 'order'));
            //余额
            $msgData = array(
                $params['type'] => array(
                    "coin" => $coin,
                    "num" => $newQuantity,
                ),
            );
            // PushLib::sendToChannelUser($marketName, $userid, array('data' => $msgData, 'type' => 'asset'));

            //入队列
            $r = Redis::getInstance()->lpush(Trade::getQueueName($marketName), json_encode(array(
                'type' => 'new',
                'data' => array(
                    'id' => $id,
                    'avl' => $params['quantity'],
                    'userid' => $userid,
                    'price' => $params['price'],
                    'type' => $params['type'],
                    'trade_type' => $params['trade_type'],
                )
            )));


            //交易对交易标记
            Redis::getInstance(3)->hset("user_trade_sign_" . $userid, str_replace('/', '_', $marketName), time());
        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }

        return $id;
    }

    public static function fakeOrders($data, $marketName, $priceC, $numberC)
    {

        foreach (['buy', 'sell'] as $type)
        {
            $sum = 0;
            $tmp = array();
            foreach($data[$type] as $k=>&$v)
            {
                $sum  = bcadd($sum, $v['n'], 8);
                if(isset($tmp[$v['p']]))
                {
                    $v['n'] = bcadd($tmp[$v['p']]['n'], $v['n'], 8);
                    unset($data[$type][$k]);
                }
                $v['n'] = Formatter::floor($v['n'], $numberC);
                $v['s'] = Formatter::floor($sum, $priceC);

                $v = [
                    $v['p'],
                    $v['n'],
                    $v['s']
                ];
                $tmp[] = $v;

            }
            $data[$type] = (array_values($tmp));
            unset($v);
        }

        $data['sell'] = array_reverse($data['sell']);



        // $orders = Redis::getInstance()->hget("entrust", $marketName);
        // $data = json_decode($orders, true)?:array('buy'=>[], 'sell'=>[]);

//        $ckey = 'trading_robot:fake_orders';
//        $fakeOrders = Redis::getInstance()->hGet($ckey, $marketName);
//        $fakeOrders = json_decode($fakeOrders, true);
//
//        if (empty($fakeOrders))
//        {
//            return $data;
//        }
//
//        foreach($fakeOrders as $o)
//        {
//            if($o['type']=='buy' && $data['buy'] && $data['buy'][0]['p']>=$o['price']){
//
//                $data['buy'][] = array('p'=>$o['price'], 'n'=>$o['quantity']);
//            }
//            elseif($o['type']=='sell' && $data['sell'] && $data['sell'][0]['p']<=$o['price'])
//            {
//                $data['sell'][] = array('p'=>$o['price'], 'n'=>$o['quantity']);
//            }
//        }
//
//        array_multisort(array_column($data['buy'], 'p'), SORT_DESC, $data['buy']);
//        array_multisort(array_column($data['sell'], 'p'), SORT_ASC, $data['sell']);
//print_r($args->data);
        //累积量
//        foreach (['buy', 'sell'] as $type)
//        {
//            $sum = 0;
//            $tmp = array();
//            foreach($data[$type] as $k=>&$v)
//            {
//                $sum    = bcadd($sum, $v['n'], 8);
//                if(isset($tmp[$v['p']]))
//                {
//                    $v['n'] = bcadd($tmp[$v['p']]['n'], $v['n'], 8);
//                    unset($data[$type][$k]);
//                }
//                $v['n'] = Formatter::floor($v['n'], $numberC);
//                $v['s'] = Formatter::floor($sum, $priceC);
//                $tmp[$v['p']] = $v;
//            }
//            $data[$type] = array_values($tmp);
//            unset($v);
//        }



        return $data;

    }


    public static function HisToS($his)
    {
        $str = explode(':', $his);

        $len = count($str);

        if ($len == 3) {
            $time = $str[0] * 3600 + $str[1] * 60 + $str[2];
        } elseif ($len == 2) {
            $time = $str[0] * 60 + $str[1];
        } elseif ($len == 1) {
            $time = $str[0];
        } else {
            $time = 0;
        }
        return $time;
    }

    public static function isHoliday()
    {
//        $holidaySwitch = Redis::getInstance(4)->get('holiday_switch');
//        if ($holidaySwitch) {
//
//        } else {
//
//        }`

        $today = date('Y-m-d');
        $currentTime = strtotime($today);
        $holiday = Redis::getInstance(3)->get('holiday');
        if ($holiday) {
            $holiday = json_decode($holiday);
            foreach ($holiday as $dayObj) {
                if (strtotime($dayObj->start_date) <= $currentTime && strtotime($dayObj->end_date . ' 23:59:59') >= $currentTime) {
                    return (array)$dayObj;
                }
            }
        } else {
            $holiday = DB::table('holiday')->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)->get();
            //写入redis
            $data = DB::table('holiday')->get();
            Redis::getInstance(3)->set('holiday', json_encode($data));
            return (array)$holiday;
        }
        return false;
    }
}
