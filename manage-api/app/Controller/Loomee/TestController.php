<?php

declare(strict_types=1);

namespace App\Controller\Loomee;

use App\Controller\AbstractController;
use App\Model\Loomee\Member;
use App\Process\NftTaskProcess;
use Hyperf\Utils\ApplicationContext;
use App\Constants\RedisCode;

class TestController extends AbstractController
{


    public function index()
    {

        // $container = ApplicationContext::getContainer();
        // $redis = $container->get(\Redis::class);

        // $redis->lpush("reptask:nft_reptile",'{"chain_id":1,"contract":"0xE1D4b259eDfdd92E8c0Bb984CA51F14524F7cA7C","cate_id":1,"collection_id":1,"owner":"sdfsfe"}');
   
        // $coinList = Coin::query()->get()->toArray();


        $params = [
            'chain_id'      => '111',
            'contract'      => '211',
            'cate_id'       => '311',
            'collection_id' => '411',
            'owner'         => '511'
        ];

        $task = new NftTaskProcess();
        $res = $task->nftReptile($params);

        return $res;
    }


    public function getDl()
    {
        $container = ApplicationContext::getContainer();
        $redis = $container->get(\Redis::class);

        $res = $redis->rpop(RedisCode::REPTILE_TASK);

        return [
            'key' => RedisCode::REPTILE_TASK,
            'time' => date("Y-m-d H:i:s"),
            'value' => $res
        ];
    }

}