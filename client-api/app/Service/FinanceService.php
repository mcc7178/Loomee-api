<?php

namespace App\Services;

use App\Services\BaseService;
use App\Model\FinanceLog;
use App\Model\UserAssetRecharges;
use App\Model\UserAssetDeals;
use App\Model\UserAssetMinings;

class FinanceService extends BaseService{

    /**
     * 新增流水日志
     *
     * @param int    $user_id
     * @param array  $data [quantity,freeze,coin,behavior,behavior_id,remark,account,status]
     * @param string $asset_type
     * @param        $time
     * @return void
     */
	public static function addLog($user_id,$data,$asset_type = 'recharge', $time = 0 ){
		if(!$user_id || !$data){
			static::addError('参数不完整',400);
			return false;
		}
		$recharge = null;
		if($asset_type == 'recharge'){
			$recharge = UserAssetRecharges::where('userid',$user_id)->where('coin',$data['coin'])->first();
		}elseif($asset_type == 'mining'){
			$recharge = UserAssetMinings::where('userid',$user_id)->where('coin',$data['coin'])->first();
		}elseif($asset_type == 'deal'){
			$recharge = UserAssetDeals::where('userid',$user_id)->where('coin',$data['coin'])->first();
		}
		
		$quantity = $data['quantity'] ?? 0;
		$freeze = $data['freeze'] ?? 0;
		$insert_data = [
			'userid' => $user_id,
			'coin' => $data['coin'],
			'old_quantity' => $recharge ? $recharge->quantity : 0,
			'old_freeze' => $recharge ? $recharge->freeze : 0,
			'new_quantity' => $recharge ? ($recharge->quantity + $quantity) : $quantity,
			'new_freeze' => $recharge ? ($recharge->freeze + $freeze) : $freeze,
			'quantity' => $quantity,
			'freeze' => $freeze,
			'behavior' => $data['behavior'],
			'behavior_id' => $data['behavior_id'],
			'remark' => $data['remark'] ?? $data['behavior'],
			'created_at' => $time ?: time(),
			'account' => $data['account'],
			'status' => $data['status']
		];
		FinanceLog::query()->forceCreate($insert_data);
		return true;
	}
}