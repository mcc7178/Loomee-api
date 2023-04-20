<?php

namespace App\Model;

use App\Services\PoolService;
use Illuminate\Database\Eloquent\Model;

class RechargeAddress extends Model
{
    protected $table = 'recharge_address';
    public $timestamps = false;

    const SYMBOL = 'coin';
    protected $guarded = [];
    const INTERNAL_COIN = PoolService::MOTHER_COIN;

    public function user()
    {
        return $this->belongsTo('App\Models\User', 'userid');
    }
}
