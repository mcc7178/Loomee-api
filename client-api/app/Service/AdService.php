<?php

namespace App\Services;

use App\Model\Ads;
use App\Model\AdsCategory;

class AdService extends BaseService{

    public static function getInviteImage($source)
    {
        $type = $source == 'pc' ? 'app_invite_image' : 'app_invite_image';
        $category = AdsCategory::query()->where('label', $type)->first();
        return Ads::query()->select(['img', 'title'])->where('status', 1)->orderByDesc('id')->where('category_id', $category->id)->get();
    }

    public static function getPosterImage($source)
    {
        $type = $source == 'pc' ? 'app_invite_download' : 'app_invite_download';
        $category = AdsCategory::query()->where('label', $type)->first();
        return Ads::query()->select(['img', 'title'])->where('status', 1)->orderByDesc('id')->where('category_id', $category->id)->get();
    }
}