<?php


namespace App\Logic;


use App\Model\MinerConf;
use App\Model\MinerUserOrder;
use App\Model\SubscribeOrder;
use App\Model\User;
use App\Services\BonusService;
use Hyperf\Redis\Redis;
use App\Model\MemberLevel;

class SubscribeLogic
{
    // public static function created($data)
    // {

    //     $data['status'] = 'processing';
    //     $res =  SubscribeOrder::query()
    //         ->create($data);
    //     $res->order_num = self::generateOrderNumber($res->id);
    //     $res->save();
    //     return $res->id;
    // }

    // public static function generateOrderNumber(int $id): string
    // {
    //     return mt_rand(10, 99)
    //         . sprintf('%010d', time() - 946656000)
    //         . sprintf('%03d', (float)microtime() * 1000)
    //         . sprintf('%03d', (int)$id % 1000);
    // }
    /*
     * start_time 开始时间戳
     * end_time   结束时间戳
     */
    public static function starSort($start_time = '',$end_time = ''){
        if(empty($start_time)){
            $start_time = strtotime('+0 day 00:00:00');
        }
        if(empty($end_time)){
            $end_time = strtotime('+0 day 23:59:59');
        }
        $arr = [];//总排名
        User::query()->orderBy('id','asc')->chunk(100,function($user) use ($start_time,$end_time,$arr){
            foreach($user as $key => $value){
                $user_id=MemberLevel::with('user')->where(['pid'=>$value['id'],'level'=>1])->pluck('user_id');
                $count = SubscribeOrder::query()->whereBetween('created_at',[$start_time,$end_time])->whereIn('user_id',$user_id)->sum('quantity');
                if($count > 0){
                    $arr[$value['id']] = $count;
                }

            }
            $uid = array_keys($arr);
            $username = User::query()->select('id','username')->whereIn('id',$uid)->get()->toArray();
            $username = array_column($username,'username','id');
            $data = [];
            foreach($arr as $k=>$v){
                $data[] = [
                    'user_id'   => $k,
                    'user_name' => $username[$k],
                    'quantity'  => $v,
                    'coin'      => 'usdt'
                ];
            }
            return $data;
        });
    }
}
