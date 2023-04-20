<?php

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class MinerLevelConf extends Model
{
    protected $table = 'miner_level_conf';

    const EN = 'en';
    const EN_NAME_FILED = 'name_en';

    const ZH = 'zh-CN';
    const ZH_TITLE_FILED = 'name';

    public function getNameAttribute()
    {
        //TODO 语言环境配置，暂时置为中文
        /*if (app('translator')->getLocale() == self::EN) {
            return $this->attributes[self::EN_NAME_FILED];
        }*/
        return $this->attributes[self::ZH_TITLE_FILED];
    }
}
