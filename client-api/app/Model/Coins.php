<?php

namespace App\Model;

use Hyperf\Database\Model\Model;
//use Laravel\Lumen\Routing\UrlGenerator;

class Coins extends Model
{
    protected $table = 'coins';
    public $timestamps = false;

    protected $hidden = [
        'name',
        'title',
        'status',
        'logo',
        'intro',
        'is_in_lock',
        'sort',
        'release_date',
        'release_num',
        'circulation_num',
        'website',
        'white_paper',
        'block_browser',
        'desc',
        'create_at',
        'admin_id',
        'is_transfer',
        'currency_decimals',
        'is_ctc',
        'is_dividends',
        'min_hold_quantity',
        'system_rank',
        'system_mining_total',
        'optimal_qunatity',
        'is_internal_transfer',
        'withdraw_check_trade_quantity',
        'withdraw_status',
    ];

    const NO_TRANSFER  = 0; //禁止转账
    const CAN_TRANSFER = 1; //可以转账

    protected $guarded = [];

    public function cointype()
    {
        return $this->belongsTo('App\Models\CoinsType', 'coin_type', 'id');
    }


    public function chain()
    {
        return $this->belongsToMany(Chain::class, 'coin_chain', 'symbol', 'chain_id', 'symbol');
    }


    public function getLogoAttribute($value)
    {
        //todo 环境变量配置
//        return (new UrlGenerator(app()))->asset("/upload/images/" . $value);
    }

    public function user_asset_recharges(){
        return $this->hasOne(UserAssetRecharges::class,'coin','symbol');
    }
}
