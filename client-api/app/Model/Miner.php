<?php

namespace App\Model;

use Hyperf\Database\Model\Model;

class Miner extends Model
{
    protected $table = 'miner';

    const DISABLE = 0; //禁用

    const ENABLE = 1; //启用
}
