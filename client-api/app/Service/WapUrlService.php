<?php

namespace App\Services;

use App\Model\WapUrl;

class WapUrlService extends BaseService{

    public static function getH5Url($page)
    {
        return WapUrl::query()->where('status', 1)->where('page', $page)->first();
    }


}