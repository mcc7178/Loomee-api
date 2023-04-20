<?php

declare(strict_types=1);

namespace App\Model;

use App\Model\Model;

class Setting extends Model
{

    protected $table = 'keyword_explain';
    protected $connection = 'default';
    protected $fillable = [];
    protected $casts = [];

    static function getOneByWhere($where)
    {
        return json_decode(self::where($where)->value('value'));
    }
}