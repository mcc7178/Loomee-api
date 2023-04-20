<?php


namespace App\Logic;


use App\Model\SubscribeBonusLog;
use App\Model\SubscribeConf;
use App\Model\SubscribeNodeConf;
use App\Model\SubscribeOrder;
use App\Model\SubscribeOrderReleaseLog;
use App\Model\SubscribeTeamConf;
use App\Model\User;
use App\Model\UserAssetRecharges;
use App\Services\BonusService;
use App\Utils\Redis;
use Doctrine\DBAL\Query\QueryException;
use mysql_xdevapi\Exception;
use Hyperf\DbConnection\Db;
use Hyperf\Utils\ApplicationContext;
use App\Exception\Handler\AppExceptionHandler;
use App\Foundation\Common\RedisService;
use App\Foundation\Facades\Log;


class SubscribeBonus
{

    /**
     * @param $quantity
     * @return string|null
     * 静态收益通政额度不放大倍数
     */
    public static function multipleQuantity($quantity)
    {

        $subscribeConf = self::getSubscribeConf();
        $multiple = $subscribeConf->multiple ?? 3;
        return bcdiv($quantity,$multiple,8);
    }

    public static function getSubscribeConf()
    {
        $keyConf = 'subscribeConf';
        $redis=Redis::getInstance();
        if($redis->exists($keyConf) )
        {
            $subscribeConf = $redis->get($keyConf);
            $subscribeConf = json_decode($subscribeConf);
        }else{
            $subscribeConf = SubscribeConf::query()->first();
            $redis->setex($keyConf, 3600, json_encode($subscribeConf));
        }
        return $subscribeConf;
    }

    public static function getSubscribeTeamConf($level = false)
    {
        $keyConf = 'subscribeTeamConf';
        $keyConf = $keyConf.'_'.$level;

        if( Redis::getInstance()->exists($keyConf) )
        {
            $subscribeTeamConf = Redis::getInstance()->get($keyConf);
            $subscribeTeamConf = json_decode($subscribeTeamConf);
        }
        else
        {
            if ($level)
            {
                $subscribeTeamConf = SubscribeTeamConf::query()->find($level);
                Redis::getInstance()->setex($keyConf, 3600, json_encode($subscribeTeamConf));
            }
        }

        return $subscribeTeamConf;
    }



    /**
     * @param $parent_id
     * @param $order
     * @param $conf
     * @return false[]
     * @throws \App\Exceptions\BaseException
     * 直推奖励
     */
    public static function inviteBonus($parent_id, $order_id, $conf)
    {
        $order = SubscribeOrder::query()->find($order_id);
//        直推奖励本金不放大3倍
//        $realQuantity = bcdiv($order->quantity,3,8);
        $realQuantity = self::multipleQuantity($order->quantity);
        // 判断上级条件 TODO

        $quantity = bcmul( $conf->recommend, $realQuantity, 8);

//        $price = MarketLogic::getMarketPrice($conf->bonus_symbol);
//        $bonusQuantity = bcdiv($quantity, $price, 8);
        if ($quantity < 0.1)
            return [
                'status' => false
            ];
        $res = self::setTeamProfit($parent_id,$quantity,$conf->bonus_symbol,'invite',$order_id,$time = date("Y-m-d",time()));
//        $res = SubscribeBonusLog::query()
//            ->create([
//                'user_id' => $parent_id,
//                'order_id' => $order->id,
//                'type' => 'invite',
//                'quantity' => $bonusQuantity,
//                'symbol' => $conf->bonus_symbol,
//                'valuation_symbol' => $order->valuation_symbol,
//                'valuation_symbol_quantity' => $quantity,
//                'price'=>$price
//            ]);

        // 更新资产
//        WalletLogic::saveUserSymbolAsset($parent_id, $order->bonus_symbol, 'subscribe_invite_bonus', $bonusQuantity, 0, $res->id);

    }


    /**
     * @throws \App\Exceptions\BaseException
     * 静态奖励
     */

    public static function staticBonus()
    {
        $conf = self::getSubscribeConf();
        var_dump("结算开始".date('Y-m-d H:i:s'));

        SubscribeOrder::query()
            ->where('not_release_quantity', '>', 0)
            ->where('status', 'processing')
            ->where('id', 30887)
            // ->where('release_at', '<>', strtotime(date("Y-m-d")))
            ->chunkById(100,function($data)use($conf){
                foreach($data as $item){
                    $quantity = bcmul($conf->static_ratio, self::multipleQuantity($item->quantity), 8); // 每天释放的量 Usdt
                    //查询用户是否设置分红
                    //var_dump($item->user_id);
                    if((new UserLogic())->isShare($item->user_id) == 0) {
                        //自己的静态收益

                        self::setTeamProfit($item->user_id,$quantity,$conf->release_symbol,$type='self',$item->id,'',1, $item);
                    }
                    //保存上级静态收益
//                        $user = User::query()->where('id',$item->user_id)->first();
                    $user = (new UserLogic())->getUserCacheData($item->user_id);

                    if (!empty($user->from_uid)){
                        if((new UserLogic())->isShare($user->from_uid) == 0) {
                            self::shareTeamProfit($user->from_uid,$quantity,$item->id);
                        }
                    }
                    var_dump("结算开始".date('Y-m-d H:i:s'));
                }
            });

        var_dump("结算结束".date('Y-m-d H:i:s'));
    }

    /**
     * 静态奖励
     */
    public static function new_staticBonus($item = [],$c_key)
    {
        if(empty($item))    return false;
        // Log::debugLog()->debug(json_encode($item));
        // Log::debugLog()->debug($item['id']);
        $redis = RedisService::getInstance('sub_user');

        try{
            $conf = self::getSubscribeConf();

            // 每天释放的量 Usdt
            $quantity = bcmul($conf->static_ratio, self::multipleQuantity($item['quantity']), 8);

            //查询用户是否设置分红
            //var_dump($item->user_id);
            if((new UserLogic())->isShare($item['user_id']) == 0)
            {
                //自己的静态收益
                self::setTeamProfit($item['user_id'],$quantity,$conf->release_symbol,$type='self',$item['id'],'',1, $item);
            }

            //保存上级静态收益
            // $user = User::query()->where('id',$item->user_id)->first();

            $user = (new UserLogic())->getUserCacheData($item['user_id']);

            if (!empty($user->from_uid)){
                if((new UserLogic())->isShare($user->from_uid) == 0) {
                    self::shareTeamProfit($user->from_uid,$quantity,$item['id']);
                }
            }

            $redis->incr("{$c_key}");
        }
        catch(\Exception $e)
        {
            // Log::debugLog()->debug(json_encode($item));
            Log::debugLog()->debug($e->getMessage()." || ".$e->getFile().' || '.$e->getLine());
            Log::debugLog()->error('queue失败订单信息为：'.json_encode($item));
            $redis->incr("staticBonus:queue_error:".date('Ymd'));
        }
    }

    // 重新处理失败的队列订单
    protected function remakeFaile()
    {
        // $redis = RedisService::getInstance('sub_user');
        // $redis->hset('user_add_month', date("Y-m",$def_time), json_encode($month));
    }

    public static function staticBonusOld()
    {
        $env = env('APP_DEBUG');
        $conf = SubscribeConf::query()->first();
        $price = MarketLogic::getMarketPrice($conf->release_symbol);
        $data = SubscribeOrder::query()
            ->where('not_release_quantity', '>', 0)
            ->where('status', 'processing')
            ->where('release_at', '<>', strtotime(date("Y-m-d")))
            ->get();
        // 静态释放
        foreach ($data as $k=> $item) {
            $quantity = bcmul($conf->static_ratio, self::multipleQuantity($item->quantity), 8); // 每天释放的量 Usdt
//            $staticQuantity = bcdiv($quantity, $price, 8); // 得到静态
            //查询用户是否设置分红
            if((new UserLogic())->isShare($item->user_id) == 0) {
                //自己的静态收益
                self::setTeamProfit($item->user_id,$quantity,$conf->release_symbol,$type='self',$item->id);
            }

            //保存上级静态收益
            $user = User::query()->where('id',$item->user_id)->first();
            if (!empty($user->from_uid)){
                //直推静态比例
//                $parentStaticQuantity = bcmul($quantity,0.3,8);
//                self::setTeamProfit($user->from_uid,$parentStaticQuantity,$conf->release_symbol,$type='release',$item->id);
//                //根据上级分静态奖励
                if((new UserLogic())->isShare($item->from_uid) == 0) {
                    //self::shareTeamProfit($user->from_uid,$quantity,$item->id);
                }
            }
        }
    }


    /**
     * @param $userid
     * @param $staticBonus
     * @return bool
     * 团队静态收益分配
     */
    public static function shareTeamProfit($userid,$staticBonus,$income_order_id){
        //根据上级ID获取关系链
        $parentArr = BonusService::getUserParent($userid);
        if(empty($parentArr))   return [];
        //组合关系数组
        $relationArr = self::getTeamRelation($parentArr);
        if(empty($relationArr))   return [];
        //进行收益划分
        $res = self::calculateTeamProfit($relationArr,$staticBonus,$income_order_id);
        return $res;
    }


    /**
     * @param $teamArray
     * @return array
     * 组合团队等级关系
     */
    public static function getTeamRelation($teamArray)
    {
        $relationArr = [];
        $levelVal = [];
        foreach ($teamArray as $k=>$v){
            $order = SubscribeOrder::query()
                ->where('user_id',$v->id)
                ->where('status','processing')
                ->where('not_release_quantity','>',0)
                ->get()->toArray();

            if (!empty($order)){
                if (empty($relationArr)){
                    array_push($relationArr,$v);
                }else{
                    $lastElement = end($relationArr);
                    if ($lastElement->subscribe_team_id <= $v->subscribe_team_id){
                        array_push($relationArr,$v);
                    }
                }
            }else
            {
                if ($v->subscribe_team_id >= 2)
                    array_push($relationArr,$v);

            }
        }
        foreach ($relationArr as $rk=>$rv){
            $levelVal[$rv->subscribe_team_id][] = $rv;
        }
        return $levelVal;
    }

    /**
     * @param $relationArr
     * @param $staticBonus
     * 计算团队收益
     */
    public static function calculateTeamProfit($relationArr,$staticBonus,$income_order_id)
    {
        $first = 0;
        $levelArr = array_keys($relationArr);
        $config = self::getSubscribeConf();
        $symbol = $config->release_symbol;

        foreach ($relationArr as $level=>$v) {
            $userid = $v[0]->id;
            $percentData = self::getSubscribeTeamConf($level);

            //首个上级直接根据静态收益比例计算收益
            if ($first == 0){
                $profit = bcmul($staticBonus,$percentData->static_bonus,8);
            }else{
                //获取上一个等级的静态收益比例
                $preLevel = $levelArr[array_search($level,$levelArr) - 1];
                $prePercentData = self::getSubscribeTeamConf($preLevel);
                //当前等级-上一个等级的静态收益
                $profit = bcmul($staticBonus,$percentData->static_bonus - $prePercentData->static_bonus,8);
            }
            //写入数据库
            self::setTeamProfit($userid,$profit,$symbol,$type='team',$income_order_id);
            //如果存在平级收益，计算平级收益
            if (isset($v[1])){
                //直推的用户才能平级
                $preUser = (new UserLogic())->getUserCacheData($userid);

                if(!empty($preUser))
                {
                    $equalityUser = $v[1]->id;
                    if ($preUser->from_uid == $equalityUser){
                        $equalityProfit = bcmul($profit,$config->peer_reward,8);
                        self::setTeamProfit($equalityUser,$equalityProfit,$symbol,$type='equal',$income_order_id);
                    }
                }
            }
            $first = 1;
        }
        return true;
    }

    /**
     * @param $userid
     * @param $profit
     * 保存静态收益
     */
    protected static function setTeamProfit($userid,$profit,$symbol,$type = 'team',$income_order_id,$time = '',$needchange = 1, $object = null)
    {
        // 历史释放数据缓存 key
        $check_key = 'subscribe_check:history_sum';
        $redis = RedisService::getInstance('sub_user');

        $conf = self::getSubscribeConf();
        $price = MarketLogic::getMarketPrice($conf->release_symbol);
        // $price = 1;
     
        // if(empty($price)){
        //     $price = 0.65;
        // }
        if (empty($profit)){
            \App\Utils\Log::info(json_encode([
                'data'=>['userid'=>$userid,'profit'=>$profit,'symbol'=>$symbol,'type'=>$type,'income_order_id'=>$income_order_id,'time'=>date('Y-m-d H:i:s'),'needchange'=>$needchange],
                'title'=>'share_profit_invite_error_'.date("Y-m-d H:i:s",time()),
                'error'=>'lqd价格获取失败',
            ]));
            return false;
        }
        if ($type == 'self')
        {
            $order = [$object];
        }
        else
        {
            $order = SubscribeOrder::query()
                ->where('user_id',$userid)
                ->where('status','processing')
                ->where('not_release_quantity','>',0)->get()->toArray();
        }
        // //总额度
        $quota = $profit;
        $bonusQuantity = 0;
        // Log::debugLog()->debug('$quota: '.$quota);
        // Log::debugLog()->debug('$order: '.json_encode($order));
        if (!empty($order)){
            DB::beginTransaction();
            try {
                foreach ($order as $k=>$v)
                {
                    if($v['not_release_quantity'] <= 0) continue;
                    if($v['status'] != 0) continue;
                    if($v['status'] != 'processing') continue;
                  
                    // 历史释放检测
                    $cache_quantity = $redis->hGet("{$check_key}", (string)$v['id']);
                    $cache_data = json_decode($cache_quantity,true) ?? [];

                    $sum_quantity = $cache_data['sum_u'] ?? 0;
                    $sum_lqd = $cache_data['sum_lqd'] ?? 0;

                    $not_release_quantity = bcsub((string)($v['quantity']*3),$sum_quantity,8);
                    if(empty($cache_data) || $not_release_quantity != $v['not_release_quantity'])
                    {
                        $sum_quantity = Db::table('subscribe_order_release_log')
                        ->where('order_id', $v['id'])
                        ->sum('release_quantity');

                        $sum_lqd = Db::table('subscribe_order_release_log')
                        ->where('order_id', $v['id'])
                        ->sum('quantity');

                        $not_release_quantity = bcsub((string)($v['quantity']*3),$sum_quantity,8);
                    }
                    // Log::debugLog()->debug('sum_quantity:'.json_encode($sum_quantity));
                    // 如果累计释放已经超出则直接修改订单状态
                    if($sum_quantity > $v['quantity']*3)
                    {
                        $update_data['release_quantity'] = $v['quantity'] * 3;
                        $update_data['release_bonus_quantity'] = $sum_quantity;
                        $update_data['not_release_quantity'] = 0;
                        $update_data['status'] = 'success';
                        SubscribeOrder::query()->where('id',$v['id'])->update($update_data);
                        continue;
                    }
                    // Log::debugLog()->debug('not_release_quantity:'.json_encode($not_release_quantity));
                    if($not_release_quantity <= 0) continue;

                    $updateData = [];
                    if ($quota <= 0){
                        break;
                    }
                    
                    if (bccomp((string)$quota, (string)$not_release_quantity, 10) != -1)
                    {
                        $quota = bcsub($quota,$not_release_quantity,8);
                        $insertReleaseQuantity = $not_release_quantity;
                        if ($needchange == 1){
                            $insertQuantity = bcdiv($not_release_quantity,$price,8);
                        }else{
                            $insertQuantity = $not_release_quantity;
                        }
                        //累加释放lqd加入到资产
                        $bonusQuantity = bcadd($insertQuantity, $bonusQuantity, 8);
                        //累计释放的usdt
                        $updateData['release_quantity'] = $v['quantity']*3;
                        //累计释放的lqd
                        $updateData['release_bonus_quantity'] = bcadd($sum_lqd,$insertQuantity,8);
                        //自减not_release_quantity(usdt)
                        $updateData['not_release_quantity'] = 0;
                        $updateData['status'] = 'success';
                    }
                    else
                    {
                        $insertReleaseQuantity = $quota;
                        if ($needchange == 1){
                            $insertQuantity = bcdiv($quota,$price,8);
                        }else{
                            $insertQuantity = $quota;
                        }
                        //累加释放lqd加入到资产
                        $bonusQuantity = bcadd($insertQuantity, $bonusQuantity, 8);
                        //累计释放的usdt
                        $updateData['release_quantity'] = bcadd($v['release_quantity'],$quota,8);
                        //累计释放的lqd
                        $updateData['release_bonus_quantity'] = bcadd($v['release_bonus_quantity'],$insertQuantity,8);
                        //自减not_release_quantity(usdt)
                        $updateData['not_release_quantity'] = bcsub($not_release_quantity,$quota,8);
                        $quota = 0;
                    }
                    $updateData['updated_at'] = time();
                    if ($type == 'self')
                        $updateData['release_at'] = strtotime(date("Y-m-d"));

                    SubscribeOrder::query()->where('id',$v['id'])->update($updateData);
                    $insertLogData = [
                        'income_order_id'=>$income_order_id,
                        'user_id'=>$userid,
                        'order_id'=>$v['id'],
                        'quantity'=>$insertQuantity,
                        'release_quantity'=>$insertReleaseQuantity,
                        'symbol'=>$v['release_symbol'],
                        'created_at'=>time(),
                        'type'=>$type,
                        'date_time'=>$time ? $time : date("Y-m-d",time())
                    ];
              
                    SubscribeOrderReleaseLog::query()->insert($insertLogData);
                }
                if ($bonusQuantity > 0){
                    WalletLogic::saveUserSymbolAsset($userid, $symbol, 'subscribe_'.$type.'_bonus', $bonusQuantity);
                }else{
                    \App\Utils\Log::info(json_encode([
                        'data'=>['userid'=>$userid,'profit'=>$profit,'symbol'=>$symbol,'type'=>$type,'income_order_id'=>$income_order_id,'time'=>date('Y-m-d H:i:s'),'needchange'=>$needchange,'bonusQuantity'=>$bonusQuantity],
                        'title'=>'share_profit_invite_error_'.date("Y-m-d H:i:s",time()),
                        'error'=>'收益价格计算为----'.$bonusQuantity,
                    ]));
                }
                DB::commit();
            }catch (\Exception $exception){
                DB::rollBack();
                \App\Utils\Log::info(json_encode([
                    'title'=>'share_profit_invite_error_'.date("Y-m-d H:i:s",time()),
                    'error'=>$exception->getMessage(),
                ]));
                return false;
            }
        }
        return true;
    }


    /**
     *加权静态收益
     */
    public static function weightedDividend()
    {
        $totalQuantity = SubscribeOrderReleaseLog::query()
            ->where('date_time','=',date('Y-m-d',strtotime('-1 day')))
            ->where('type','=','self')
            ->sum('quantity');
        if ($totalQuantity == 0){
            return false;
        }
        $conf = SubscribeConf::query()->first();
        $price = MarketLogic::getMarketPrice($conf->release_symbol);

        //节点人数
        $userNum = DB::select(
            'SELECT
            um.subscribe_node_id,
            um.num,
            c.percent 
        FROM
            subscribe_node_conf AS c
            JOIN ( SELECT ANY_VALUE(u.id),u.subscribe_node_id, COUNT( id ) AS num FROM `user` AS u GROUP BY u.subscribe_node_id ) AS um ON c.id = um.subscribe_node_id 
        WHERE
            c.percent > 0 
        ORDER BY
            um.subscribe_node_id ASC'
        );
        $config = self::getSubscribeConf();
        $count = count($userNum);
        //静态收益
        $staticQuantity = $totalQuantity;
        $profit = 0;
        for ($i=0;$i<$count;$i++){
            for ($j=$i+1;$j<$count;$j++){
                $userNum[$i]->num += $userNum[$j]->num;
            }
            $profit += $staticQuantity * $userNum[$i]->percent / $userNum[$i]->num;
            $profit = sprintf("%.8f",$profit);
            $insertLog = [
                'total_quantity'=>$totalQuantity,
                'config_static_ratio'=> $config->static_ratio,
                'static_quantity'=>$staticQuantity,
                'subscribe_node_conf_id'=>$userNum[$i]->subscribe_node_id,
                'percent'=>$userNum[$i]->percent,
                'profit'=>$profit,
                'created_at'=>time(),
                'date_time'=>date("Y-m-d",time())
            ];
            $res = DB::table('subscribe_weighted_dividend_log')->insert($insertLog);
            if ($res){
                //加权释放的单位是lqd ,需要转成usdt计算
                $profit_usdt = bcmul($profit,$price,8);
                $nodeUser = User::query()->select(['id','subscribe_node_id','is_share'])->where('subscribe_node_id',$userNum[$i]->subscribe_node_id)->get();
                foreach ($nodeUser as $row){
                    if($row->is_share == 0){
                        self::setTeamProfit($row->id,$profit_usdt,$config->release_symbol,'weighted',0,'',1);
                    }
                }
            }
        }
    }


    /**
     * 每日英雄日榜加权收益
     */
    public static function starProfit()
    {
        $start = strtotime(date('Y-m-d',strtotime('-1 day')).'00:00:00');
        $end   = strtotime(date('Y-m-d',strtotime('-1 day')).'23:59:59');
        //全网昨日新增总收益
        $totalQuantity = DB::table('subscribe_order')
            ->where('created_at','>=',$start)
            ->where('created_at','<=',$end)
            ->sum('quantity');

        $conf = SubscribeConf::query()->first()->toArray();

        $totalQuantity = bcmul($totalQuantity,$conf['start_ratio'],8);

        $staticQuantity = $totalQuantity;
        //获取排名
        $sort = SubscribeLogic::starSort($start,$end);
        for ($i=0;$i<5;$i++){
            $num = $i+1;
            if (isset($sort[$i])){
                $percent = $conf['start_'.$num];
                $profit = bcmul($staticQuantity,$percent,8);
                if((new UserLogic())->isShare($sort[$i]['user_id']) == 0) {
                    self::setTeamProfit($sort[$i]['user_id'],$profit,$conf['release_symbol'],'start',0);
                }
            }
        }
    }


    public static function getDayList()
    {
        $res = [];
        $start = strtotime(date('Y-m-d',strtotime('-1 day')).'00:00:00');
        $end   = strtotime(date('Y-m-d',strtotime('-1 day')).'23:59:59');
        $sort = SubscribeLogic::starSort($start,$end);
        $conf = SubscribeConf::query()->first()->toArray();
        if(!empty($sort)){
            for ($i=0;$i<5;$i++){
                if (isset($sort[$i])){
                    $res[$i]['user_id'] = $sort[$i]['user_id'];
                    $res[$i]['user_name'] = $sort[$i]['user_name'];
                    $res[$i]['quantity'] = $sort[$i]['quantity'];
                    $res[$i]['coin'] = $sort[$i]['coin'];
                    $profit = SubscribeOrderReleaseLog::query()
                        ->select('quantity','symbol')
                        ->where('date_time',date("Y-m-d",time()))
                        ->where('type','start')
                        ->where('user_id',$sort[$i]['user_id'])
                        ->first();
                    $res[$i]['profit'] = $profit->quantity ?? (float)0;
                    $res[$i]['symbol'] = $profit->symbol ?? '';
                }
            }
        }
        return $res;
    }


    /**
     * 获取小区、大区业绩
     * @param int $user_id  用户id
     * @param bool $getChildIds true=只获取小区用户id
     * @return array
     */
    public static function getUserMinMaxAmount($user_id,  $field = 'from_uid')
    {
        $mineUserAmount = SubscribeOrder::query()->where('user_id', $user_id)->sum('quantity')?:0;
        $inviteData = User::query()->where($field, $user_id)->get();
        if (count($inviteData) < 1){
            return ['min' => 0, 'max' => 0, 'min_user_id' => [],'invite'=>0, 'max_user_id' => [], 'invite_number' =>count($inviteData), 'my' => $mineUserAmount];
        }
        $inviteUserAmount = SubscribeOrder::query()->whereIn('user_id', array_keys($inviteData->keyBy('id')->toArray()))->sum('quantity')?:0;
        foreach ($inviteData as $inviteDatum){
            $resAmount = self::getUserTeamAmount($inviteDatum->id, 0);
            if ($resAmount['status'])
                $inviteAmount[$inviteDatum->id] = $resAmount;
            else
                continue;
        }
        if (!isset($inviteAmount))
            return ['min' => 0, 'max' => 0, 'min_user_id' => [], 'max_user_id' => [],'invite'=>0, 'invite_number' =>count($inviteData), 'my' => $mineUserAmount ];
        $max_id = self::checkMaxAndIds($inviteAmount);

        $max_x = $inviteAmount[$max_id];
        $max_amount = ($inviteAmount[$max_id]);
        unset($inviteAmount[$max_id]);
        $amounts = array_column($inviteAmount, 'amount');
        $amounts = array_sum($amounts);


        return [
            'min' => $amounts,
            'max' => $max_amount['amount'],
            'invite' =>$inviteUserAmount,
            'min_user_id' => $inviteAmount,
            'max_user_id' => $max_x,
            'invite_number' =>count($inviteData),
            'my' => $mineUserAmount
        ];
    }


    /**
     * 获取单人团队总业绩
     * @param $user_id
     * @param int $lvl
     * @return array
     */
    public static function getUserTeamAmount($user_id, $lvl = 0)
    {
        // 每个人的所有下级
        // $child = BonusService::getUserChild($user_id, $lvl);
        $child = self::getUserChild2($user_id, $lvl);

        if (!$child){
            return ['status' => false, 'msg' => '无下级'];
        }
        $ids = array_column(json_decode(json_encode($child), true), 'id');

        $amount = SubscribeOrder::query()->whereIn('user_id', $ids)->sum('quantity');

        return ['amount' => $amount, 'ids' => $ids, 'status' => true];
    }

    /**
     * 获取最大区的id
     * @param $data
     * @return int|string
     */
    private static function checkMaxAndIds($data)
    {
        $num = 0;
        $id = array_keys($data)[0];
        foreach ($data as $k => $v) {
            if ($v['amount'] > $num){
                $num = $v['amount'];
                $id = $k;
            }
        }
        return $id;
    }


    /**
     * 获取所有的下级, 0包括自己, 1不包括自己
     * @param $node_id
     * @param int $lvl
     * @return mixed
     */
    public static function getUserChild2($node_id, $lvl = 0)
    {
        $sql = "
            WITH RECURSIVE affiliate (id,from_uid,lvl) AS
                    (
                        SELECT id,from_uid,0 lvl FROM user WHERE id = :id
                        UNION ALL
                        SELECT u.id, u.from_uid,lvl+1 FROM affiliate AS a
                        JOIN user AS u ON u.from_uid = a.id
                    )
                    SELECT id,lvl FROM affiliate where lvl >= :lvl
        ";
        return DB::select($sql, [':id' => $node_id, ':lvl' => $lvl]);
    }
}
