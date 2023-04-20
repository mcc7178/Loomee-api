<?php

namespace App\Services;
use App\Model\RechargeAddress;
use App\Model\RechargeLog;

class RechargeService extends BaseService{
   
    public static function getRechargeLog(array $where){
        $recharge_log = RechargeLog::where($where)->first();
        if(!$recharge_log){
            return false;
        }
        return $recharge_log->toArray();
    }

    public static function getRechargeAddress($search){
        $address = RechargeAddress::where($search)->first();
        if(!$address){
            return false;
        }
        return $address->toArray();
    }

    public static function createRechargeLog($data){
        $result = RechargeLog::insert($data);
        return $result;
    }
    
}