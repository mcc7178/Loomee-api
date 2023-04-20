<?php

namespace App\Services;

use App\Model\Exchange;
use App\Model\UserAssetMinings;
use App\Model\UserBonusDynamicLog;
use App\Model\UserBonusLog;
use App\Model\WormholeConf;
use App\Services\BaseService;
use App\Model\User;
use App\Model\UserLoginLog;
use App\Lib\Redis;
use App\Model\UserAssetRecharges;
use Illuminate\Support\Facades\DB;
use App\Model\UserAssetDeals;
use Illuminate\Support\Facades\Log;

class AssetService extends BaseService{

	public static function getCoinsPrice()
	{
		//所有交易对价格
        $marketPrice = Redis::getInstance()->hgetall("trade_market");
        $coinsPrice  = array();
        foreach($marketPrice as $v)
        {
        	$v = json_decode($v, true);
    		list($coin, $sector) = explode('/', $v['m']);
    		$coinsPrice[$coin] = isset($coinsPrice[$coin])?max($coinsPrice[$coin], $v['cny']):$v['cny'];

        }

        $sectorCoinPrice = Redis::getInstance()->hgetall("rmb_price");
        foreach($sectorCoinPrice as $k=>$v)
        {
        	if($v==0)
        	{
        		unset($sectorCoinPrice[$k]);
        	}
        }
        $coinsPrice = array_merge($coinsPrice, $sectorCoinPrice);

        //btc rmb Price
        if(isset($coinsPrice['btc']))
        {
        	$btcRmbPrice = $coinsPrice['btc'];
        }
        else
        {
        	$btcRmbPrice = Redis::getInstance()->hget("rmb_price", 'btc');
        }
        return array($coinsPrice, $btcRmbPrice);
	}

    public static function getCoinPrice()
    {
        //所有交易对价格
        $marketPrice = Redis::getInstance()->hgetall("trade_market");
        $coinsPrice  = array();
        foreach($marketPrice as $v)
        {
            $v = json_decode($v, true);
            list($coin, $sector) = explode('/', $v['m']);
            $coinsPrice[$coin] = isset($coinsPrice[$coin])?max($coinsPrice[$coin], $v['cny']):$v['cny'];

        }

        $sectorCoinPrice = Redis::getInstance()->hgetall("rmb_price");
        foreach($sectorCoinPrice as $k=>$v)
        {
            if($v==0)
            {
                unset($sectorCoinPrice[$k]);
            }
        }
        $coinsPrice = array_merge($coinsPrice, $sectorCoinPrice);

        //btc rmb Price
        if(isset($coinsPrice['usdt']))
        {
            $btcRmbPrice = $coinsPrice['usdt'];
        }
        else
        {
            $btcRmbPrice = Redis::getInstance()->hget("rmb_price", 'usdt');
        }
        return array($coinsPrice, $btcRmbPrice);
    }

	public static function updateAssetRecharge($user_id,$coin,$quantity = 0,$freeze = 0){

		$search = [
			'userid' => $user_id,
			'coin' => $coin
		];

		$result = UserAssetRecharges::query()->where($search)->lockForUpdate()->first();
		if(!$result){
			$insert_data = [
				'userid' => $user_id,
				'coin' => $coin,
				'quantity' => $quantity,
				'freeze' => $freeze
			];
			return UserAssetRecharges::create($insert_data);
		}else{
            $update_data = [
                'freeze' => DB::raw("freeze + $freeze"),
                'quantity' => DB::raw("quantity + $quantity")
            ];
            $result = UserAssetRecharges::query()-> where($search)->update($update_data);
        }
//		$result->quantity += $quantity;
//		$result->freeze += $freeze;
//		$result->save();
		return $result;
	}

    public static function updateAssetMining($user_id,$coin,$quantity = 0,$freeze = 0){
        $search = [
            'userid' => $user_id,
            'coin' => $coin
        ];
        $result = UserAssetMinings::where($search)->lockForUpdate()->first();
        if(!$result){
            $insert_data = [
                'userid' => $user_id,
                'coin' => $coin,
                'quantity' => $quantity,
                'freeze' => $freeze
            ];
            return UserAssetMinings::create($insert_data);
        }else{
            $update_data = [
                'freeze' => DB::raw("freeze + $freeze"),
                'quantity' => DB::raw("quantity + $quantity")
            ];
            $result = UserAssetMinings::query()->where($search)->update($update_data);
        }

        return $result;
    }

    public static function updateAssetDeal($user_id,$coin,$quantity = 0,$freeze = 0){
        $search = [
            'userid' => $user_id,
            'coin' => $coin
        ];
        $result = UserAssetDeals::where($search)->lockForUpdate()->first();
        if(!$result){
            $insert_data = [
                'userid' => $user_id,
                'coin' => $coin,
                'quantity' => $quantity,
                'freeze' => $freeze
            ];
            return UserAssetDeals::create($insert_data);
        }else{
            $update_data = [
                'freeze' => DB::raw("freeze + $freeze"),
                'quantity' => DB::raw("quantity + $quantity")
            ];
            $result = UserAssetDeals::where($search)->update($update_data);
        }

        return $result;
    }

    // 获取账户余额
    public static function getUserBalance($userid, $symbol, $asset)
    {
        switch ($asset)
        {
            case "recharge":
                $model = UserAssetRecharges::class;
                break;
            case "deal":
                $model = UserAssetDeals::class;
                break;
            case "mining":
                $model = UserAssetMinings::class;
        }
        return $model::query()->where('userid', $userid)
            ->where('coin', $symbol)
            ->first();
    }

    public static function insertBonusLog($data)
    {
        return UserBonusLog::query()->insert($data);
    }

    public static function insertDynamicLog($data)
    {
        return UserBonusDynamicLog::query()->insertGetId($data);
    }

    /**
     * @param     $user_id
     * @param     $coin
     * @param int $isLock
     * @return false|\Illuminate\Database\Concerns\BuildsQueries|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|mixed|object
     */
    public static function getUserAsset($user_id, $coin = 'usdt', $isLock = 0)
    {
        $search = [
            'userid' => $user_id,
            'coin' => strtolower($coin),
        ];
        $user_asset = UserAssetDeals::query()->when($isLock, function ($query) {
            return $query->lockForUpdate();
        })->firstOrCreate($search, ['freeze' => 0]);

        if (!$user_asset) {
            static::addError('用户冻结资金数据异常！', 500);
            return false;
        }
        return $user_asset;
    }

    public static function getLibraCoinToUsdtPrice($coin)
    {
        if ($coin === PoolService::MOTHER_COIN) {
            return Exchange::query()->value('current_price') ?? 0;
        } else {
            return WormholeConf::query()->value('price');
        }
    }

}
