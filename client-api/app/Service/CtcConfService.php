<?php


namespace App\Services;


use App\Lib\Redis;
use App\Model\CtcConf;

class CtcConfService extends BaseService
{
    // conf
    public static function getConf()
    {
        if (Redis::getInstance(4)->exists('ctc_conf')){
            $data = Redis::getInstance(4)->get('ctc_conf');
            return json_decode($data);
        }
        $conf = CtcConf::query()->find(1);
        Redis::getInstance(4)->set('ctc_conf', json_encode($conf));
        return $conf;
    }
}