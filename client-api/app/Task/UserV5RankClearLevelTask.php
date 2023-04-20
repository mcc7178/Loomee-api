<?php
namespace App\Task;

use App\Log;
use App\Logic\UserLogic;
use App\Model\SubscribeOrder;
use App\Model\User;
use App\Model\UserProfitRank;
use App\Model\UserSubAssess;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Crontab\Annotation\Crontab;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;


class UserV5RankClearLevelTask
{
    /**
     * @Inject()
     * @var \Hyperf\Contract\StdoutLoggerInterface
     */
    private $logger;

    public function execute()
    {
        $list = User::query()
        ->where('is_node_whitelist', 0)
        ->where('subscribe_node_id', '>', 1)
        ->get();

        var_dump("清理级别开始处理".date('Y-m-d H:i:s'));
        User::query()
            ->where('is_node_whitelist', 0)
            ->update([
                'subscribe_node_id' => 1
            ]);
        var_dump("清理级别结束处理".date('Y-m-d H:i:s'));

        foreach($list as $item)
        {
            Db::table('user_clear_node_level')
                ->insert([
                'user_id' => $item->id,
                'user_node_id' => $item->subscribe_node_id,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        
    }
}
