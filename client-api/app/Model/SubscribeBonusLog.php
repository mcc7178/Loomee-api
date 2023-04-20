<?php


namespace App\Model;


use Illuminate\Database\Eloquent\Model;

class SubscribeBonusLog extends Model
{
    protected $table = 'subscribe_bonus_log';

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'order_id',
        'type',
        'quantity',
        'symbol',
        'valuation_symbol',
        'valuation_symbol_quantity',
        'price',
        'created_at',
        'ratio'
    ];
}
