<?php
declare(strict_types=1);

namespace App\Process;


use App\Logic\SubscribeBonus;
use App\Model\User;
use App\Services\BonusService;
use App\Utils\Redis;
use Hyperf\Process\AbstractProcess;
use Psr\Container\ContainerInterface;
use Hyperf\Logger\LoggerFactory;
use App\Foundation\Facades\Log;

class UpgradeUserTest
{

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    protected $signature = 'upgrade';
    protected $description = '升級队列';


    public function goTask($arr)
    {
        if(empty($arr) || !array_key_exists('uid',$arr)) return null;
        // $list = User::query()->select('id')->where('id',$arr['uid'])->get()->toArray();
     
        // foreach($list as $k=>$v)
        // {
        //     $this->upTest($v['id']);
        // }

        return $this->upTest($arr['uid']);
    }

    /**
     * 调试测试
     */
    public function upTest($userID)
    {

        $parent = BonusService::getUserParent($userID);
     
        if(!empty($parent))
        {
            //查询每一级父类的所有子集的交易量是否达标,达标则升级
            foreach($parent as $key => $value)
            {
                $userInfo = User::query()->where('id',$value->id)->first();
                if ($userInfo->node_status == 1)    continue;

                if(!empty($value->id))
                {
                    $minMaxAmount = SubscribeBonus::getUserMinMaxAmount($value->id);
           
                    $lv = BonusService::getUserLv($minMaxAmount['min']);

                    if($lv > 0 && $lv > $value->subscribe_team_id ){
                        User::query()->where('id',$value->id)->update(['subscribe_team_id'=>$lv]);

                        $str = '用户id：'.$value->id. ' lv:'. $lv.  '  old(lv:'.$value->subscribe_team_id.')'.PHP_EOL.'minMaxAmount：'.json_encode($minMaxAmount);
                        Log::debugLog()->debug($str);

                        return $str;
                    }
                    
                }
            }

        }
    }




}
