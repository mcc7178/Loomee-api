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


class SubMonthMinRankTask
{
    /**
     * @Inject()
     * @var \Hyperf\Contract\StdoutLoggerInterface
     */
    private $logger;

    public function execute()
    {
        $def_time = time();

        // $redis = ApplicationContext::getContainer()->get(\Hyperf\Redis\Redis::class);
        $redis = RedisService::getInstance('sub_user');
        var_dump("开始处理--- ".date('Y-m-d H:i:s'));

        $startTime = strtotime(date("Y-m",$def_time));
        $endTime   = time();

        $date = $redis->get('SubMonthMinRankTask');
        if (!$date)
        {
            $redis->set('SubMonthMinRankTask', $startTime);
        }
        else
        {
            if ($date != $startTime)
            {
                $redis->del('user_order');
                $redis->set('SubMonthMinRankTask', $startTime);
            }
        }

        $config = Db::table("config_sub_rank")->first();

        $level = Db::table('subscribe_team_conf')->get()->keyBy('id');

        $user = User::query()->select('id','username','subscribe_node_id','subscribe_team_id', 'is_node_whitelist')
            ->where('node_status' , 0)
            ->where('subscribe_team_id','>=',$config->level ?: 6)
            ->get()->toArray();

        // TODO
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


            $month[] = $data = [
                "user_id"  => $value['id'],
                "username" => $value['username'],
                "quantity" => $min,
                "order"    => $min,
                'max'      => $max,
                'lvl'      => $level[$value['subscribe_team_id']]->name,
                'subscribe_team_id'      => $value['subscribe_team_id'],
                'is_node_whitelist' => $value['is_node_whitelist'],
                'min_user_id' => $childUserId['min_user_id'],
                'max_user_id' => $childUserId['max_user_id'],
            ];


            var_dump(json_encode($data));

            if ($redis->hExists('user_order', 'user:'.$value['id']))
            {
                $res = $redis->hGet('user_order', 'user:'.$value['id']);
                $res = json_decode($res, true);
                if ($res['order'] != $res['quantity'])
                {
                    $data['order'] = $res['order'];
                }
            }

            $redis->hset('user_order','user:'.$value['id'],json_encode($data));
        }

//        $redis->del('user_order');

//        if ($month)
//        {
//            foreach ($month as $item) {
//                $redis->hset('user_order','user:'.$item['user_id'],json_encode($item));
//            }
//        }



        var_dump("结束处理".date('Y-m-d H:i:s'));
    }

}
