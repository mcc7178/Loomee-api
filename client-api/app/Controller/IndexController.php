<?php

declare(strict_types=1);

namespace App\Controller;
use App\Logic\SubscribeBonus;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\AutoController;
use App\Model\MemberLevel;
use App\Exception\DbException;
use App\Model\SubscribeOrderReleaseLog;
use App\Foundation\Facades\Log;


/**
 * @AutoController
 */
class IndexController
{
    public function index()
    {
        var_dump('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
//        \App\Model\User::query()->where('is_level',0)->where('id','>=',1000000)->orderBy('id','asc')->chunk(10,function($list){
//            foreach($list as $v){
//                $data=[];
//                $parents=\App\Model\MemberLevel::where('user_id',$v['from_uid'])->get();
//                foreach($parents as $val){
//                    $child_uid=\App\Model\User::where('from_uid',$val['pid'])->value('id');
//                    $data[]=['user_id'=>$v['id'],'pid'=>$val['pid'],'child_uid'=>$child_uid,'from_uid'=>$val['from_uid'],'level'=>$val['level']+1];
//                }
//                $data[]=['user_id'=>$v['id'], 'pid'=>$v['from_uid'],'child_uid'=>$v['id'],'from_uid'=>$v['from_uid'],'level'=>0];
//                Db::beginTransaction();
//                try{
//                    if(!empty($data)){
//                        $memberLevel=new \App\Model\MemberLevel;
//                        $res=$memberLevel->insert($data);
//                        if($res){
//                            $v->is_level=1;
//                            $ret=$v->save();
//                            if(!$ret){
//                               throw new DbException('更新用户'.$v['id'].'推荐关系失败');
//                            }
//                        }
//                    }
//                    Db::commit();
//                    \App\Utils\Log::info('用户id'.$v['id'].'推荐关系更新完成');
//                } catch(\Throwable $ex){
//                    Db::rollBack();
//                    \App\Utils\Log::info('用户id'.$v['id'].'推荐关系更新'.$ex->getMessage());
//                }
//            }
//        });
//         $sql = "
//         WITH RECURSIVE affiliate (id,node_id,lvl) AS
//                 (
//                     SELECT id,node_id,0 lvl FROM user WHERE id = :id
//                     UNION ALL
//                     SELECT u.id, u.node_id,lvl+1 FROM affiliate AS a
//                     JOIN user AS u ON a.node_id = u.id
//                 )
//                 SELECT id,node_id,lvl FROM affiliate where lvl >= :lvl
//    ";
//         $data=DB::select($sql, [':id' => 1000008, ':lvl' => 4]);
//         var_dump(json_encode($data));
//         $res=Db::table('member_level')->where('pid',1000008)->where('level','>=',4)->orderBy('level','asc')->select('user_id as id','level as lvl')->get();
//         var_dump(json_encode($res));
        // (new SubscribeBonus)->staticBonus();
    }




    public function check_a()
    {
        $log = new SubscribeOrderReleaseLog();

        $list = $log->where('date_time','2022-05-27')
            ->orderBy('created_at','asc')
            ->get()
            ->toArray();

        $data = [];

        $user = [];
        foreach($list as $k=>$v)
        {
            $key = md5($v['income_order_id'].'-'.$v['order_id']);
            if(array_key_exists($key,$data))
            {
                $key = $v['user_id'];
                if(array_key_exists($key,$user))
                {
                    $user[$key] = [
                        'quantity'          => bcadd($user[$key]['quantity'],$v['quantity'],8),
                        'release_quantity'  => bcadd($user[$key]['release_quantity'],$v['release_quantity'],8),
                        'order_id'          => $user[$key]['order_id'].','.$v['order_id'],
                        'order_id'          => $user[$key]['order_id'].','.$v['order_id'],
                        'kkk_id'            => $user[$key]['kkk_id'].','.$v['id'],
                    ];
                }  
                else
                {
                    $user[$key] = [
                        'quantity'          => $v['quantity'],
                        'release_quantity'  => $v['release_quantity'],
                        'order_id'          => $v['order_id'],
                        'kkk_id'            => $v['id'],
                    ];
                }
            }
            else
            {
                $data[$key] = $v;
            }

        }

        // return [
        //     'before'=>$before,
        //     'after' =>$after
        // ];

        $error = [];
        $insert = [];
        foreach($user as $k=>$v)
        {
            $insert[] = [
                'user_id' => $k,
                'quantity' => $v['quantity'],
                'release_quantity' => $v['release_quantity'],
                'order_id' => $v['order_id'],
                'kkk_id' => $v['kkk_id'],
            ];
        }

        try{
            Db::table('test_a')->insert($insert);
        }
        catch(\Exception $e)
        {
            // Log::debugLog()->debug($e->getMessage()." || ".$e->getFile().' || '.$e->getLine());
        }

        return $insert;
    }




    public function check()
    {
        // $list = Db::select("SELECT a.id, a.user_id, a.quantity, a.kkk_id, b.quantity AS b_quantity  FROM test_a a LEFT JOIN user_asset_recharges b ON a.user_id = b.userid  WHERE b.`coin` LIKE 'lqd'   ORDER BY  `userid`;");

        // $should = '0';
        // $kan_ok = '0';
        // $kkk_id = '';
        // $user = '';
        // try{
        //     foreach($list as $k=>$v)
        //     {
        //         // $should = bcadd($should,$v['quantity'],8);
        //         // if($v['quantity'] <= $v['b_quantity'])
        //         // {
        //         //     $kan_ok = bcadd($kan_ok,$v['quantity'],8);
        //         // }

        //         $should = bcadd($should,$v->quantity,8);
        //         if($v->quantity <= $v->b_quantity)
        //         {
        //             $kan_ok = bcadd($kan_ok,$v->quantity,8);

        //             if($kkk_id == '')
        //                 $kkk_id = $v->kkk_id;
        //             else
        //                 $kkk_id = $kkk_id.','.$v->kkk_id;

        //             Db::table('user_asset_recharges')
        //                 ->where('userid',$v->user_id)
        //                 ->where('coin','lqd')
        //                 ->update(['quantity'=>($v->b_quantity - $v->quantity)]);

        //             $user .= $v->user_id.',';
        //         }
        //     }
        // }
        // catch(\Exception $e)
        // {
        //     Log::debugLog()->debug($e->getMessage()." || ".$e->getFile().' || '.$e->getLine());
        // }

        // $del_id = explode(',',$kkk_id);
   
        // Db::table('subscribe_order_release_log')->whereIn('id',$del_id)->delete();
        // return [
        //     'should' => $should, 
        //     'kan_ok' => $kan_ok, 
        //     'kkk_id' => count($del_id),
        //     'user' => $user,
        // ];
    }





}