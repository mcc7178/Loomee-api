<?php
declare(strict_types=1);

namespace App\Process;


use App\Logic\SubscribeBonus;
use App\Model\User;
use App\Model\UserLevelUpLogs;
use App\Services\BonusService;
use App\Utils\Redis;
use Hyperf\Process\AbstractProcess;
use Psr\Container\ContainerInterface;
use Hyperf\Logger\LoggerFactory;
use App\Foundation\Facades\Log;

class UpgradeUserProcess extends AbstractProcess
{

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    protected $signature = 'upgrade';
    protected $description = '升級队列';

    public function handle(): void
    {
        while(true)
        {
            $this->runMain();
            sleep(1);
        }
    }

    public function runMain()
    {
        // Log::debugLog()->debug('UpgradeUserProcess  升级检测'.date('Y-m-d H:i:s'));
        try{
            $redis = Redis::getInstance();
            $userID = $redis->lPop('upgrade_user_order');
//            Log::debugLog()->debug('UpgradeUserProcess  $userID:'.$userID);
            if (!$userID) return null;

            $parent = BonusService::getUserParent($userID);
//            Log::debugLog()->debug('UpgradeUserProcess  $parent:'.json_encode($parent));
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
                        //                            $sum = SubscribeOrder::query()
                        //                                ->whereIn('user_id', $user_id)
                        //                                ->sum('quantity');
                        //传递数值判断是否可提升等级，返回0不做任何处理
                        $lv = BonusService::getUserLv($minMaxAmount['min']);

                        // \Log::info('给上级升级---自动的 、'.$userID. 'lv:'. $lv);
                        // Log::debugLog()->debug('给上级升级---自动的 、'.$value->id. 'lv:'. $lv.  '以前的级别：'.$value->subscribe_team_id);

                        //如果大于0则为提升等级
                        if($lv > 0 && $lv > $value->subscribe_team_id ){
                            User::query()->where('id',$value->id)->update(['subscribe_team_id'=>$lv]);
                            
                            Log::debugLog()->debug('给上级升级---自动的 、'.$value->id. 'lv:'. $lv.  '以前的级别：'.$value->subscribe_team_id);

                            $log_info = [
                                'user_id' => $value->id,
                                'new_level' => $lv,
                                'old_level' => $value->subscribe_team_id,
                                'level_up_info' => json_encode($minMaxAmount),
                                'created_at' => time(),
                            ];
                            // 记录升级日志
                            UserLevelUpLogs::insert($log_info);
                        }
                    }
                }
            }
        }
        catch(\Exception $e)
        {
            Log::debugLog()->debug($e->getMessage()." || ".$e->getFile().' || '.$e->getLine());
        }

    }



}
