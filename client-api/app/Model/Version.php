<?php
namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class Version extends Model
{
    protected $table = 'version';
    public $timestamps = true;

    protected $guarded =[];

    const CREATED_AT = 'created_time';

    protected $dateFormat = 'U';

    const TYPE_IOS = 'ios';

    protected $appends = [
        'url'
    ];


    const TYPE_ANDROID = 'android';

    public function getQrcodeAttribute($value)
    {
        return "/upload/images/".$value;
    }

    public function getUrlAttribute()
    {
        return $this->attributes['download_url'];
    }


    public function setQrcodeAttribute($value)
    {
        return str_replace("/upload/images/", '', $value);
    }
}
