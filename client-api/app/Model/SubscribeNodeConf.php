<?php


namespace App\Model;


use Illuminate\Database\Eloquent\Model;

class SubscribeNodeConf extends Model
{
    protected $table = 'subscribe_node_conf';

    const EN = 'en';
    const EN_NAME_FILED = 'name_en';

    const ZH = 'zh-CN';
    const ZH_TITLE_FILED = 'name';

    public function getNameAttribute()
    {
        if( app('translator')->getLocale() == self::EN ) {
            return $this->attributes[self::EN_NAME_FILED];
        }
        return  $this->attributes[self::ZH_TITLE_FILED];
    }
}
