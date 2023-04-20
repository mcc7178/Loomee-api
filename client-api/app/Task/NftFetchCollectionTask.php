<?php

namespace App\Task;

use App\Model\Collection\Collection;
use App\Service\NftReptileService;
use App\Foundation\Facades\Log;

class NftFetchCollectionTask
{
    public function handle()
    {
        $list = Collection::query()->get()->toArray();
        foreach ($list as $item) {
            (new NftReptileService())->handle(json_encode(['contract' => $item['contract'], 'cate_id' => $item['cate_id'], 'chain_id' => $item['chain_id']]));
            sleep(30);
        }
    }
}