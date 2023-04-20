<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;


class Article extends Model
{
    protected $table = 'article';
    public $timestamps = false;

    protected $guarded =[];

    protected $appends = [
        'content', 'title'
    ];

    protected $casts = [
        'create_time' => 'datetime'
    ];

    const EN = 'en';
    const EN_TITLE_FILED = 'title_en';
    const EN_CONTENT_FILED = 'content_en';

    const ZH = 'zh-CN';
    const ZH_TITLE_FILED = 'title_zh';
    const EZH_CONTENT_FILED = 'content_zh';

    protected $hidden  = [
        'title_en', 'content_en', 'content_zh', 'title_zh','status', 'sort'
    ];

    public function getContentAttribute()
    {
        if( app('translator')->getLocale() == self::EN )
            return  $this->attributes[self::EN_CONTENT_FILED];
        return  $this->attributes[self::EZH_CONTENT_FILED];
    }


    public function getTitleAttribute()
    {
        if( app('translator')->getLocale() == self::EN )
            return  $this->attributes[self::EN_TITLE_FILED];
        return  $this->attributes[self::ZH_TITLE_FILED];
    }



}
