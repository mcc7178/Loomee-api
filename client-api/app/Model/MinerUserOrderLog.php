<?php

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class MinerUserOrderLog extends Model
{
    protected $table = 'miner_user_order_log';

    public $dateFormat = 'U';

    protected $casts = [
        //'award_info' => 'array',
    ];

    protected $fillable = [
        'type',
        'user_id',
        'order_num',
        'coin',
        'award_quantity',
        'date',
        'created_at',
    ];

    public function getCreatedAtAttribute($val)
    {
        return date("Y-m-d H:i", $val);
    }
}
