<?php

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class WithdrawAddress extends Model
{
    protected $table = 'withdraw_address';
    public $timestamps = false;

    protected $appends = [
        'symbol'
    ];
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo('App\Models\User', 'userid');
    }

    public function getCoinAttribute($val)
    {
        return strtoupper($val);
    }

    public function getSymbolAttribute()
    {
        return $this->attributes['coin'];
    }
}
