<?php
namespace App\Process;

use App\Logic\UserLogic;
use App\Model\SubscribeOrder;
use App\Model\User;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Crontab\Annotation\Crontab;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Utils\ApplicationContext;
use App\Foundation\Common\RedisService;

class SubMonthMinRankAllTest
{
    /**
     * @Inject()
     * @var \Hyperf\Contract\StdoutLoggerInterface
     */
    private $logger;

    public function execute($arr = [])
    {
        $def_time = time();
        if(array_key_exists('uid',$arr))
            $userID = $arr['uid'];
        else
            $userID = 2231165;

        $startTime = strtotime(date("Y-m",$def_time));
        $endTime   = time();
        $level = Db::table('subscribe_team_conf')->get()->keyBy('id');
        $nodeLevel = Db::table('subscribe_node_conf')->get()->keyBy('id');

        $user = User::query()->select('id','username','subscribe_node_id','subscribe_team_id', 'is_node_whitelist')
            ->where('node_status' , 0)
            ->where('id' , $userID)
            ->where('id' , '>', 3)
            ->get()->toArray();

        $profit =[];
        $month = [];
        foreach($user as $key=>$value){
            $profit = $this->getMaxMinProfit($value['id'],$startTime,$endTime);
            $month[] = [
                "user_id"  => $value['id'],
                "username" => $value['username'],
                "quantity" => $profit['min'],
                "order"    => $profit['min'],
                'max'      => $profit['max'],
                'lvl'      => $level[$value['subscribe_team_id']]->name,
                'subscribe_team_id' => $value['subscribe_team_id'],
                'subscribe_node_id' => $value['subscribe_node_id'],
                'subscribe_node_name' => $nodeLevel[$value['subscribe_node_id']]->name,
                'is_node_whitelist' => $value['is_node_whitelist']
            ];
        }
        
        return [
            'level'=>$level,
            'nodeLevel'=>$nodeLevel,
            'user'=>$user,
            'profit'=>$profit,
            'month'=>$month,
        ];
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
        $user_list = [];
        if(!empty($child) && count($child) >= 1)
        {
            foreach($child as $key=>$value)
            {
                $grandchild = (new UserLogic())->getUserChild($value['id']);//大小区业绩不包含自己
                $user_list = array_merge($user_list,$grandchild);
                if(!empty($grandchild))
                {
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
        return [
            'min'=>$min,
            'max'=>$max, 
            'user_list'=>implode(',',array_column($user_list,'id')), 
        ];
    }

}
