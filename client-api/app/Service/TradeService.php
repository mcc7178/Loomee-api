<?php

namespace App\Services;
use App\Lib\Push as PushLib;
use App\Model\UserAssetDeals;
use App\Model\User;
use App\Lib\Redis;
use App\Model\UserAssetRecharges;
use Illuminate\Support\Facades\Schema;
use App\Services\CommonService;
use App\Model\Trade;
use App\Model\Markets;
use App\Services\MarketService;
use App\Exceptions\BaseException;
use Illuminate\Support\Facades\DB;

class TradeService extends BaseService{


    public static $model_prefix = 'App\Models\Trade';
    public static $table_prefix = 'trade_';

    //根据交易对获取对应model和表明
    public static function getTable($trade){
        $trade_arr = explode('/',$trade);
        if(count($trade_arr) != 2){
            static::addError('trade.symbol invalid format',400);
            return false;
        }

        $trade_model = self::$model_prefix.ucfirst($trade_arr[0]).ucfirst($trade_arr[1]);
        $table_name = self::$table_prefix.$trade_arr[0].'_'.$trade_arr[1];
        return [$trade_model,$table_name];
    }

    public static function getCurrentTrades($search,$page,$page_size,array $markets){


        // $table_model = self::getTable($market);
        // if(!$table_model){
        //     return false;
        // }

        // list($trade_model,$table_name) = $table_model;
        // if(!Schema::hasTable($table_name)){
        //     static::addError('该交易对不存在',400);
        //     return false;
        // }
        $search[] = ['type','!=','market'];
        $fields = 'created_time,id,type,trade_type,quantity,price,volume,market,amount,make_price,status';
        $result = self::getMultiMarketList($markets,$search,$fields,$page,$page_size);
        if(!$result){
            return [];
        }

        $markets_conf = MarketService::getAllMarketConf();

        foreach($result as &$item){
            $market = strtolower($item['market']);
            if(!isset($markets_conf[$market])){
                $markets_conf[$market] = MarketService::getMarketConf($market);
            }
            $market_arr = explode('/',$market);
            $unsettled = $item['quantity'] - $item['volume'];
            $item['unsettled'] = number_format($unsettled,$markets_conf[$market]->decimals_number,'.','');
            $item['price'] = number_format($item['price'],$markets_conf[$market]->decimals_price,'.','');
            $item['quantity'] = number_format($item['quantity'],$markets_conf[$market]->decimals_number,'.','');
            $item['amount'] = number_format($item['amount'],8,'.','');
            $item['volume'] = number_format($item['volume'],$markets_conf[$market]->decimals_number,'.','');
            $item['make_price'] = number_format($item['make_price'],$markets_conf[$market]->decimals_price,'.','');
            $item['trade_type'] = $item['trade_type'] == 'limit' ? '限价' : '市价';
            $market = strtoupper($item['market']);
            $item['trade'] = $market;
            $item['_type'] = $item['type'];
            $item['type'] = $item['type'] == 'buy' ? '买入' : '卖出';
            $item['trade_status'] =  $item['status'];
            $item['status'] = $item['status'] == 0 ? '交易中' : '--';
            $item['price_unit'] = strtoupper($market_arr[1]);
            $item['quantity_unit'] = strtoupper($market_arr[0]);


        }
        return $result;
    }

    public static function getHistoryTrades($search,$page,$page_size,array $markets){
        // $table_model = self::getTable($market);
        // if(!$table_model){
        //     return false;
        // }

        // list($trade_model,$table_name) = $table_model;
        // $trade_log_model = $trade_model.'Log';
        // $table_name .= '_log';
        // if(!Schema::hasTable($table_name)){
        //     static::addError('该交易对不存在',400);
        //     return false;
        // }

        $fields = 'created_time,id,type,trade_type,quantity,price,volume,market,amount,make_price,status,fee';
        $result = self::getMultiMarketList($markets,$search,$fields,$page,$page_size,0);
        if(!$result){
            return [];
        }

        $markets_conf = MarketService::getAllMarketConf();

        foreach($result as &$item){
            $market = strtolower($item['market']);
            if(!isset($markets_conf[$market])){
                $markets_conf[$market] = MarketService::getMarketConf($market);
            }
            $market_arr = explode('/',$market);

            $item['quantity'] = number_format($item['quantity'],$markets_conf[$market]->decimals_number,'.','');
            $item['make_price'] = number_format($item['make_price'],$markets_conf[$market]->decimals_price,'.','');
            $item['amount'] = number_format($item['amount'],8,'.','');
            $item['volume'] = number_format($item['volume'],$markets_conf[$market]->decimals_number,'.','');
            $item['trade'] = strtoupper($item['market']);
            $item['_type'] = $item['type'];
            if($item['trade_type'] === 'market'){
                $item['price'] = '市价';
            }else{
                $item['price'] = number_format($item['price'],$markets_conf[$market]->decimals_price,'.','');
            }
            $item['trade_type'] = $item['trade_type'] == 'limit' ? '限价' : '市价';
            $item['type'] = $item['type'] == 'buy' ? '买入' : '卖出';
            $item['trade_status'] =  $item['status'] === 'revocation' ? 2 : 1;
            $item['status'] = $item['status'] === 'revocation' ? '已撤销' : '已完成';

            $item['price_unit'] = strtoupper($market_arr[1]);
            $item['quantity_unit'] = strtoupper($market_arr[0]);
        }
        return $result;
    }

    public static function revokeTrade($market,$user_id,$trade_id){

        \DB::beginTransaction();
        try{
            //改主表
            // $trade = TradeLog::where(['id' => $trade_id,'user_id' => $user_id])->first();
            // if(!$trade){
            //     throw new \Exception('委托单不存在或已交易完成');
            // }
            // $market = $trade->market;
            // $trade_id = $trade->trade_id;
            // $trade->status = 'revocation';
            // $trade->save();

            //找子表
            $table_model = self::getTable($market);
            if(!$table_model){
                throw new BaseException('trade.symbol invalid format');
            }

            list($trade_model,$table_name) = $table_model;
            if(!Schema::hasTable($table_name)){
                throw new BaseException('trade.symbol not exist');
            }

            //考虑到订单有可能被撮合，这里是否需要加入写锁
            $trade = \DB::table($table_name)->where('id',$trade_id)->where('user_id',$user_id)->lockForUpdate()->first();
            if(!$trade){
                throw new BaseException('trade.order not exist');
            }

            $trade = json_decode(json_encode($trade),true);

            $market_arr = explode('/',$market);
            $coin = $trade['type'] == 'sell' ? $market_arr[0] : $market_arr[1];
            $user_asset = self::getUserAsset($user_id,$coin);
            if(!$user_asset){
                return false;
            }

            //计算待成交量
            if(bccomp($trade['quantity'], bcadd($trade['unsettled'], $trade['volume'], 8), 8)!==0){
                throw new BaseException('trade.order less');
            }

            $unsettled = $trade['unsettled'];
            //买入额外计算
            if($trade['type'] == 'buy'){
                //买入 冻结金额 = 数量*单价
                $unsettled = bcmul($trade['unsettled'], $trade['price'], 8);
            }

            //验证数据是否正常
            if(bccomp($user_asset['freeze'] , $unsettled, 8) === -1){
                throw new BaseException('trade.user asset less');
            }

            //修改冻结资金和可用余额
            UserAssetRecharges::where(['userid'=>$user_id,'coin'=>$coin])->update(array(
                'quantity'=>\Db::raw('quantity+'.$unsettled),
                'freeze'=>\Db::raw('freeze-'.$unsettled)
            ));

            //将该撤销信息存入日志表
            $trade_log = $trade;
            $trade_log['status'] = 'revocation';
            $trade_log['created_time'] = time();
            $trade_log['end_time'] = time();
            $log_table = $table_name.'_log';
            \DB::table($log_table)->insert($trade_log);

            //从挂单表删除该数据
            \DB::table($table_name)->where('id',$trade_id)->delete();

            $msgData = array(
                $trade['type'] => array(
                    "coin"=> $coin,
                    "num"=> bcadd($unsettled, $user_asset['quantity'],8 ),
                ),
            );
            PushLib::sendToChannelUser($market, $user_id, array('data'=>$msgData, 'type'=>'asset'));

            \DB::commit();

            //入队列
            $r = Redis::getInstance()->lpush(Trade::getQueueName($trade['market']), json_encode(array(
                'type'=>'cancel',
                'data'=>array(
                    'id'=>$trade['id'],
                    'avl'=>$trade['quantity'],
                    'userid'=>$trade['user_id'],
                    'price'=>$trade['price'],
                    'type'=>$trade['type'],
                    'trade_type'=>$trade['trade_type'],
                )
            )));
        }
        catch(BaseException $e)
        {
            \DB::rollBack();
            throw $e;
        }
        catch(\Exception $e){
            \Log::info('订单撤销错误:'.$e->getFile().$e->getLine().$e->getMessage());
            \DB::rollBack();
            throw new BaseException('trade.system error');
        }

        return true;
    }

    public static function getUserAsset($user_id,$coin){
        $search = [
            'userid' => $user_id,
            'coin' => $coin
        ];
        $user_asset = UserAssetDeals::where($search)->lockForUpdate()->first();
        if(!$user_asset){
            static::addError('trade.user asset freeze',500);
            return false;
        }
        return $user_asset->toArray();
    }


    public static function tradeDetail($market,$trade_id,$type,$user_id){

        //找主表
        // $trade = TradeLog::where(['id' => $trade_id,'user_id' => $user_id])->first();
        // if(!$trade){
        //     throw new \Exception('委托单不存在或已交易完成');
        // }
        // $market = $trade->market;
        // $trade_id = $trade->trade_id;

        $market_arr = explode('/',$market);
        if(count($market_arr) != 2){
            static::addError('trade.symbol invalid format',400);
            return false;
        }

        $table_name = 'finish_trade_'.$market_arr[0].'_'.$market_arr[1].'_log';
        if(!Schema::hasTable($table_name)){
            static::addError('trade.symbol not exist',400);
            return false;
        }

        $type_id = $type.'_trade_id';
        $query = \DB::table($table_name)->leftJoin((new User)->getTable().' as sell_user','sell_user.id','=',$table_name.'.sell_user_id')
                                ->leftJoin((new User)->getTable().' as buy_user','buy_user.id','=',$table_name.'.buy_user_id');
        $query->where($table_name.'.'.$type_id,$trade_id);
        $query->where($table_name.'.'.$type.'_user_id',$user_id);
        $result = $query->select(
            'sell_user.username as sell_user_name',
            'buy_user.username as buy_user_name',
            $table_name.'.created_at',
            $table_name.'.quantity',
            $table_name.'.market',
            $table_name.'.price',
            $table_name.'.sell_fee',
            $table_name.'.buy_fee',
            $table_name.'.amount'
        )->orderBy($table_name.'.created_at','asc')->get()->toArray();
        $result = json_decode(json_encode($result),true);
        if(!$result){
            return [];
        }

        $market = MarketService::getMarketConf($market);

        foreach($result as &$item){
            //$market_arr = explode('/',$item['market']);
            //$item['quantity'] = $item['quantity'] . ' '.$market_arr[0];
            $item['sell_fee'] = number_format($item['sell_fee'],8,'.','');
            $item['buy_fee'] = number_format($item['buy_fee'],8,'.','');
            //$item['price'] = $item['price']. ' '.$market_arr[1];
            //$item['amount'] = $item['amount'] . ' '.$market_arr[1];
            $item['price'] = number_format($item['price'],$market->decimals_price,'.','');
            $item['quantity'] = number_format($item['quantity'],$market->decimals_number,'.','');
            $item['amount'] = number_format($item['amount'],8,'.','');
        }
        return $result;
    }

    public static function getOneTrade($market,$trade_id,$user_id,$status){
        $table_model = self::getTable($market);
        if(!$table_model){
            return false;
        }

        list($trade_model,$table_name) = $table_model;
        if($status != 0){
            $table_name .= '_log';
        }
        if(!Schema::hasTable($table_name)){
            static::addError('trade.symbol not exist',400);
            return false;
        }

        if($status){
            $status = $status == 2 ? 'revocation' : 'finish';
        }

        $market = MarketService::getMarketConf($market);

        $query = \DB::table($table_name)->where('id',$trade_id)->where('user_id',$user_id)->where('status',$status);
        $result = $query->select(
            'price as trade_price',
            'quantity as trade_quantity',
            'volume as trade_volume',
            'amount as trade_amount',
            'make_price as trade_make_price',
            'fee as trade_fee',
            'trade_type'
        )->first();
        if(!$result){
            static::addError('trade.data error',404);
            return false;
        }
        $result->trade_price = number_format($result->trade_price,$market->decimals_price,'.','');
        $result->trade_quantity = number_format($result->trade_quantity,$market->decimals_number,'.','');
        $result->trade_volume = number_format($result->trade_volume,$market->decimals_number,'.','');
        $result->trade_amount = number_format($result->trade_amount,8,'.','');
        $result->trade_make_price = number_format($result->trade_make_price,$market->decimals_price,'.','');
        $result->trade_fee = number_format($result->trade_fee,8,'.','');
        $result->trade_type = $result->trade_type == 'limit' ? '限价' : '市价';
        return $result;

    }

    public static function getHuoBiMarketTrade($market){
        if(!$market){
            static::addError('order.please pass symbol',400);
            return false;
        }

        $market = str_replace('/','',$market);
        $url = 'https://api.huobi.pro/market/trade?symbol='.$market;
        $data = CommonService::curl_request($url);
        if (!$data){
            static::addError('trade.data no exist',400);
            return  false;
        }
        $data = json_decode($data,true);
        if ($data && $data['status'] !== 'ok') {
            $error = $data['err-code'].':'.$data['err-msg'];
            static::addError($error,400);
            return  false;

        }

        $data = $data['tick'];
        return $data;
    }

    public static function getMultiMarketList(Array $markets, array $search, string $fields, $page,$page_size=10,$is_current = 1)
    {
        //$markets = ['btc/usdt','roll/usdt'];
        $subSqls = [];
        foreach($markets as $market)
        {
            $market = str_replace('/','_',$market);
            $sql = 'SELECT '.$fields.' FROM trade_'.$market;
            $sql = $is_current ? $sql : $sql.'_log';
            if($search){
                $sql .= ' where ';
                $count = count($search);
                foreach($search as $key => $item){
                    $sql .= $item[0].$item[1]."'".$item[2]."'";
                    if(($key+1) < $count){
                        $sql .=' and ';
                    }
                }
            }

            $subSqls[] = $sql;
        }

        $subSql = implode(' union ', $subSqls);
        $sql = $subSql.' order by created_time desc ';

        $limit = ($page-1)*$page_size;

        $sql = $sql.'limit '.$limit.','.$page_size;

        $result = \DB::select($sql);
        $result = json_decode(json_encode($result),true);
        return $result;
    }

    public static function getMultiMarketTotal(Array $markets, array $search,$is_current = 1)
    {
        $subSqls = [];
        foreach($markets as $market)
        {
            $market = str_replace('/','_',$market);
            $sql = 'SELECT count(*) as total FROM trade_'.$market;
            $sql = $is_current ? $sql : $sql.'_log';
            if($search){
                $sql .= ' where ';
                $count = count($search);
                foreach($search as $key => $item){
                    $sql .= $item[0].$item[1]."'".$item[2]."'";
                    if(($key+1) < $count){
                        $sql .=' and ';
                    }
                }
            }

            $subSqls[] = $sql;
        }

        $subSql = implode(' union ', $subSqls);

        $result = \DB::select($subSql);
        $result = json_decode(json_encode($result),true);
        $totals = 0;
        foreach($result as $item){
            $totals += $item['total'];
        }
        return $totals;
    }
}
