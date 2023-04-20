<?php

namespace App\Task;

use App\Foundation\Facades\Log;
use App\Model\Auth\Member;
use App\Model\Product\Product;

class AssociateUserAddress
{
    public function handle()
    {
        $list = Product::query()->where('owner_id', 0)->get()->toArray();
        if ($list) {
            $user = Member::query()->whereIn('address', array_unique(array_column($list, 'owner')))->get()->keyBy('address')->toArray();
            foreach ($list as $item) {
                if (!empty($user[$item['owner']])) {
                    $model = Product::query()->find($item['id']);
                    $model->owner_id = $user[$item['owner']]['id'];
                    $model->save();
                    Log::codeDebug()->info("fill product owner_id,product_id:{$item['id']},user:{$item['owner']},owner_id:{$user[$item['owner']]['id']}");
                }
            }
        }
    }
}