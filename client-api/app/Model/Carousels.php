<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;


class Carousels extends Model
{
    protected $table = 'carousel';
    public $timestamps = false;

    protected $appends = [
        'image'
    ];

    const EN = 'en';
    const EN_FILED = 'en_image';

    const ZH = 'zh-CN';
    const ZH_FILED = 'zh_image';

    protected $hidden  = [
        'created_at', 'status', 'en_image', 'zh_image'
    ];

    protected $guarded =[];


    public function getImageAttribute()
    {
        $url = 'https://api.fll13.vip';
        if( app('translator')->getLocale() == self::EN )
            return  $url.$this->attributes[self::EN_FILED];
        return  $url.$this->attributes[self::ZH_FILED];

    }
}
