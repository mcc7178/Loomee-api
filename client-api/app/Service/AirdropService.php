<?php


namespace App\Services;

use App\Model\Activity;

class AirdropService extends BaseService
{

    public static $quantity = 400;

    protected static $inviteQuantity = 200;
    public static $asset_methods = [
        'asset' => 'updateAssetRecharge',
        'lock' => 'updateAssetMining',
        'trade' => 'updateAssetDeal'
    ];


    public static function airdropRoll($userid, $invite_id)
    {
        return ;

        //空投赠送
        $search = [
            'type' => 'air_drop',
            'status' => 1,
            'is_realname' => 0
        ];
        //$time = time();
        // $reg_activity = Activity::where($search)->where('start_time','<=',$time)->where('stop_time','>=',$time)->whereRaw('amount > subscribed_amount')->get();
        // foreach($reg_activity as $item){
        //     $method = self::$asset_methods[$item->account] ?? false;
        //     if(!$method){
        //         \Log::info('错误的空投赠送账户类型:'.$item->account);
        //         continue;
        //     }
        //     if($item->amount <= $item->subscribed_amount || $item->amount < ($item->quantity + $item->subscribed_amount)){
        //         continue;
        //     }

        //     $quantity = $item->quantity;
        //     $freeze = 0;
        //     if($item->is_lock){
        //         $quantity = 0;
        //         $freeze = $item->quantity;
        //     }
        //     $recharge = AssetService::$method($userid,$item->symbol,$quantity, $freeze);
        //     $finance_data = [
        //         'coin' => $item->symbol,
        //         'behavior' => 'air_drop_give',
        //         'behavior_id' => 0,
        //         'remark' => '空投赠送',
        //         'account' => $item->account,
        //         'status' => 1,
        //         'freeze' => $freeze,
        //         'quantity' => $quantity
        //     ];

        //     $log = FinanceService::addLog($userid,$finance_data);
        //     Activity::where('id',$item->id)->increment('subscribed_amount',$item->quantity);
        // }

        //注册赠送
        $search['type'] = 'reg_give';
        $time = time();
        $reg_activity = Activity::where($search)->where('start_time','<=',$time)->where('stop_time','>=',$time)->whereRaw('amount > subscribed_amount')->get();
        if($reg_activity){
            foreach($reg_activity as $item){
                $method = self::$asset_methods[$item->account] ?? false;
                if(!$method){
                    \Log::info('错误的注册赠送账户类型:'.$item->account);
                    continue;
                }
                if($item->amount <= $item->subscribed_amount || $item->amount < ($item->quantity + $item->subscribed_amount)){
                    continue;
                }

                $quantity = $item->quantity;
                $freeze = 0;
                if($item->is_lock){
                    $quantity = 0;
                    $freeze = $item->quantity;
                }
                $recharge = AssetService::$method($userid,$item->symbol,$quantity, $freeze);
                $finance_data = [
                    'coin' => $item->symbol,
                    'behavior' => 'reg_gift_lock',
                    'behavior_id' => 0,
                    'remark' => '注册赠送',
                    'account' => $item->account,
                    'status' => 1,
                    'freeze' => $freeze,
                    'quantity' => $quantity
                ];

                $log = FinanceService::addLog($userid,$finance_data,Activity::ACCOUNT[$item->account]);
                Activity::where('id',$item->id)->increment('subscribed_amount',$item->quantity);
            }
        }


        //邀请赠送
        if ($invite_id>1)
        {
            $search['type'] = 'invite_give';
            $reg_activity = Activity::where($search)->where('start_time','<=',$time)->where('stop_time','>=',$time)->whereRaw('amount > subscribed_amount')->get();
            if($reg_activity){
                foreach($reg_activity as $item){
                    $method = self::$asset_methods[$item->account] ?? false;
                    if(!$method){
                        \Log::info('错误的邀请赠送账户类型:'.$item->account);
                        continue;
                    }
                    if($item->amount <= $item->subscribed_amount  || $item->amount < ($item->quantity + $item->subscribed_amount)){
                        continue;
                    }

                    $quantity = $item->quantity;
                    $freeze = 0;
                    if($item->is_lock){
                        $quantity = 0;
                        $freeze = $item->quantity;
                    }
                    $recharge = AssetService::$method($invite_id,$item->symbol,$quantity, $freeze);
                    $finance_data = [
                        'coin' => $item->symbol,
                        'behavior' => 'invite_give',
                        'behavior_id' => 0,
                        'remark' => '邀请赠送',
                        'account' => $item->account,
                        'status' => 1,
                        'freeze' => $freeze,
                        'quantity' => $quantity
                    ];
                    $log = FinanceService::addLog($invite_id,$finance_data,Activity::ACCOUNT[$item->account]);
                    Activity::where('id',$item->id)->increment('subscribed_amount',$item->quantity);
                }
            }
        }
    }
}
