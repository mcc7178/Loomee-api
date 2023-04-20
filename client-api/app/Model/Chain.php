<?php

namespace App\Model;

use Hyperf\Database\Model\Model;
//use Laravel\Lumen\Routing\UrlGenerator;

class Chain extends Model
{
    protected $table = 'chain';
    public $timestamps = false;

    protected $guarded = [];

    public function getNameAttribute($val)
    {
        return strtoupper($val);
    }


    public function getLogoAttribute($value)
    {
        //todo 环境变量配置
        //return (new UrlGenerator(app()))->asset("/upload/images/" . $value);
    }
}
