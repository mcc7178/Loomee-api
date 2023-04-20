<?php

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class MinerUserOrder extends Model
{
    protected $table = 'miner_user_order';

    protected $dateFormat = 'U';

    protected $casts = [
        'award_info' => 'array',
    ];

    public function fil_award()
    {
        return $this->hasMany(MinerUserOrderLog::class,'order_num','order_num');
    }

    protected $fillable = [
        'miner_id',
        'order_num',
        'user_id',
        'quantity',
        'created_at',
        'updated_at',
        'status',
        'power',
        'release_usdt_quantity',
        'release_fil_quantity',
        'wait_release_fil_quantity',
        'date',
        'award_date',
        'award_num',
        'award_num_use',
        'award_info',
        'validity_period',
        'encapsulation_period',
        'encapsulation_period_profit',
        'full_calculation_profit',
        'manage_fee',
        'details',
        'pledge_t',
        'pledge_return_ratio',
    ];

    public function getCreatedAtAttribute($val)
    {
        return date("Y-m-d H:i",$val);
    }

}
