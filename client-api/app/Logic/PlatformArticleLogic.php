<?php


namespace App\Logic;


use App\Model\CoinChain;
use App\Model\Coins;
use App\Model\RechargeAddress;
use Illuminate\Support\Facades\DB;

class PlatformArticleLogic
{
    const PLATFORM = [
        'jinse' => '金色财经',
    ];

    public static function PlatformArticleList($data)
    {
        foreach ($data as &$val) {
            unset($val['thumbnails_pics']);
            unset($val['topic_url']);
            unset($val['detail']);
            unset($val['thumbnails_pics']);
            $val['platform'] = !empty($val['platform']) ? self::PLATFORM[$val['platform']] : '';
        }
        return $data;
    }

    public static function PlatformArticleDetails($details)
    {
        $details['platform'] = self::PLATFORM[$details['platform']];
        return $details;
    }
}
