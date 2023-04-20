<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\AbstractController;
use App\Task\GetUserRechargeAddressTask;
use App\Task\KlineWeekMonthTask;
use App\Task\MemberLevel;
use App\Task\MinerAllBonusTask;
use App\Task\MinerOrder;
use App\Task\MinerOrderAward;
use App\Task\MinerOrderAwardLog;
use App\Task\MinerTeamBonus;
use App\Task\PlatformArticle;
use App\Task\StarProfit;
use App\Task\StaticBonus;
use App\Task\WeightedDividend;
use App\Task\MinerOrderSettlement;
use App\Task\NewMinerAllBonusTask;
use App\Task\NewMinerTeamBonus;
use App\Task\NewMinerOrderTeamAllProfit;
use App\Task\SubscribeOrderCheck;
use App\Task\StaticBonusFailQueueTask;

use App\Task\SubMonthMinRankAllTask;
use App\Task\SubMonthMinRankCacheTask;
use App\Task\SubMonthMinRankTask;
use App\Task\UserV5RankClearLevelTask;
use App\Task\UserV5RankUpLevelTask;
use App\Foundation\Facades\Log;



class GoTaskController extends AbstractController
{

    protected $cache_model = [
        'GetUserRechargeAddressTask'    => GetUserRechargeAddressTask::class,       
        'KlineWeekMonthTask'            => KlineWeekMonthTask::class,
        'MemberLevel'                   => MemberLevel::class,          //
        'MinerAllBonusTask'             => MinerAllBonusTask::class,
        'MinerOrder'                    => MinerOrder::class,
        'MinerOrderAward'               => MinerOrderAward::class,
        'MinerOrderAwardLog'            => MinerOrderAwardLog::class,
        'MinerTeamBonus'                => MinerTeamBonus::class,
        'PlatformArticle'               => PlatformArticle::class,
        'StarProfit'                    => StarProfit::class,               
        'StaticBonus'                   => StaticBonus::class,              
        'WeightedDividend'              => WeightedDividend::class,             
        'MinerOrderSettlement'          => MinerOrderSettlement::class,             
        'NewMinerAllBonusTask'          => NewMinerAllBonusTask::class,             
        'NewMinerTeamBonus'             => NewMinerTeamBonus::class,             
        'NewMinerOrderTeamAllProfit'    => NewMinerOrderTeamAllProfit::class,             
        'StaticBonusFailQueueTask'      => StaticBonusFailQueueTask::class,          
        'SubscribeOrderCheck'           => SubscribeOrderCheck::class,                  
    ];

    protected $level_a_model = [
        'SubMonthMinRankAllTask'    => SubMonthMinRankAllTask::class,            
        'SubMonthMinRankCacheTask'  => SubMonthMinRankCacheTask::class,            
        'SubMonthMinRankTask'       => SubMonthMinRankTask::class,            
        'UserV5RankClearLevelTask'  => UserV5RankClearLevelTask::class,            
        'UserV5RankUpLevelTask'     => UserV5RankUpLevelTask::class,            
    ];

    

    /**
     * 执行task
     *
     * @return void
     */
    public function task()
    {
        $all = $this->request->all();

        $model_name = $all['task'] ?? '';
        
        if(array_key_exists($model_name,$this->cache_model))
        {
            try{
                $model = new $this->cache_model[$model_name];
                if($model_name == 'StaticBonusFailQueueTask')
                    $model->handle($all['date'] ?? '');
                else
                    $model->handle();
            }
            catch(\Exception $e)
            {
                Log::crontabLog()->error($e->getMessage()." || ".$e->getFile().' || '.$e->getLine());
            }

            return 'complete：';
        }
        elseif(array_key_exists($model_name,$this->level_a_model))
        {
            try{
                $model = new $this->level_a_model[$model_name];
                $model->execute();
            }
            catch(\Exception $e)
            {
                Log::crontabLog()->error($e->getMessage()." || ".$e->getFile().' || '.$e->getLine());
            }

            return 'complete';
        }
        else
        {
            return 'no task';
        }
        
    }

    







}
