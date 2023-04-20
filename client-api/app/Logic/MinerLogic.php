<?php


namespace App\Logic;


use App\Model\MinerUserOrder;

class MinerLogic
{
    public static function created($data)
    {

        $data['status'] = 'in_package';
        $res = MinerUserOrder::query()
            ->create($data);
        return $res->id;
    }

    public static function generateOrderNumber(int $id): string
    {
        return mt_rand(10, 99)
            . sprintf('%010d', time() - 946656000)
            . sprintf('%03d', (float)microtime() * 1000)
            . sprintf('%03d', (int)$id % 1000);
    }
}
