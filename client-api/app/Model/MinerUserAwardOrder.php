<?php

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class MinerUserAwardOrder extends Model
{
    protected $table = 'miner_user_award_order';

    public $dateFormat = 'U';

    protected $casts = [
        //'award_info' => 'array',
    ];

    protected $fillable = [
        'user_id',
        'order_num',
        'power',
        'award_first',
        'award_remain',
        'award_day',
        'award_remain_day',
        'date',
        'award_date',
        'created_at',
        'updated_at',
        'type'
    ];
}
