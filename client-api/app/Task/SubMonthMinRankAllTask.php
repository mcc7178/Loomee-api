<?php
namespace App\Task;

use App\Logic\UserLogic;
use App\Model\SubscribeOrder;
use App\Model\User;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Crontab\Annotation\Crontab;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Utils\ApplicationContext;
use App\Foundation\Common\RedisService;

class SubMonthMinRankAllTask
{
    /**
     * @Inject()
     * @var \Hyperf\Contract\StdoutLoggerInterface
     */
    private $logger;

    public function execute()
    {
        // if(date('i')%4 == 0)
            $def_time = time();
        // else
        //     $def_time = strtotime("2022-4-22");
        var_dump('SubMonthMinRankAllTask');
        // $redis = ApplicationContext::getContainer()->get(\Hyperf\Redis\Redis::class);
        $redis = RedisService::getInstance('sub_user');

        $startTime = strtotime(date("Y-m",$def_time));
        $endTime   = time();
        $level = Db::table('subscribe_team_conf')->get()->keyBy('id');
        $nodeLevel = Db::table('subscribe_node_conf')->get()->keyBy('id');

        $user = User::query()->select('id','username','subscribe_node_id','subscribe_team_id', 'is_node_whitelist')
            ->where('node_status' , 0)
            ->where('id' , '>', 3)
            ->get()->toArray();

        $month = [];
        foreach($user as $key=>$value){
            // 先拿到大小区
            $childUserId = (new UserLogic())->getUserMinMaxAmount($value['id']);

            $minId = $maxId = [];
            foreach ($childUserId['min_user_id'] as $item) {
                if (isset($item['ids']))
                {
                    $ids = $item['ids'];
                    foreach ($ids as $id) {
                        array_push($minId, $id);
                    }
                }
            }

            foreach ($childUserId['max_user_id'] as $k => $item) {
                if ($k == 'ids')
                {
                    foreach ($item as $v) {
                        array_push($maxId, $v);
                    }
                }
            }

            // 再获取大小区的业绩
            $min= $max = 0;
            if ($minId)
                $min = (new UserLogic())->getMaxMinProfit($minId,$startTime,$endTime);
            if ($maxId)
                $max = (new UserLogic())->getMaxMinProfit($maxId,$startTime,$endTime);

            $x = $month[] = [
                "user_id"  => $value['id'],
                "username" => $value['username'],
                "quantity" => $min,
                "order"    => $min,
                'max'      => $max,
                'lvl'      => $level[$value['subscribe_team_id']]->name,
                'subscribe_team_id' => $value['subscribe_team_id'],
                'subscribe_node_id' => $value['subscribe_node_id'],
                'subscribe_node_name' => $nodeLevel[$value['subscribe_node_id']]->name,
                'is_node_whitelist' => $value['is_node_whitelist']
            ];

//            var_dump(json_encode($x));
        }

        var_dump(count($month));

        $redis->hset('user_add_month', date("Y-m",$def_time), json_encode($month));
        var_dump("结束处理".date('Y-m-d H:i:s'));
    }

    /**
     * 获取认购大小区业绩
     * @param $userId
     * @return array
     */
    public function getMaxMinProfit($userId,$startTime,$endTime){

        $child = User::query()->select('id')->where(['from_uid'=>$userId])->get()->toArray();
        $min = $max = 0;
        $profit = [];
        if(!empty($child) && count($child) >= 1){
            foreach($child as $key=>$value){
                $grandchild = (new UserLogic())->getUserChild($value['id']);//大小区业绩不包含自己
                if(!empty($grandchild)){
                    $grandchildId = array_column($grandchild,'id');
                    $quantity = SubscribeOrder::query()->whereIn('user_id',$grandchildId)->whereBetween('created_at',[$startTime,$endTime])->sum('quantity')??0;
                    $profit[] = $quantity;
                }
            }
            if(count($profit) == 1){//一条线的算大区业绩
                $max = $profit[0];
                $min = 0;
            }else{
                rsort($profit);
                $max = $profit[0];
                unset($profit[0]);
                $min = array_sum($profit);
            }
        }
        return ['min'=>$min,'max'=>$max];
    }

}
