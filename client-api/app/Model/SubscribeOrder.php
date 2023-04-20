<?php
namespace App\Model;


use Hyperf\DbConnection\Model\Model;

class SubscribeOrder extends Model
{
    protected $table = 'subscribe_order';

    public $dateFormat = 'U';


    protected $casts = [
        'symbol_info_json' => 'array',
    ];

    const STATUS = [
        'wait'       => '-1',
        'success'    => '1',
        'processing' => '0'
    ];

    public function getCreatedAtAttribute($val)
    {
        return date("Y-m-d H:i:s",$val);
    }
    protected $fillable = [
        'order_num',
        'user_id',
        'quantity',
        'created_at',
        'status',
        'symbol_info_json',
        'not_release_quantity',
        'valuation_symbol',
        'release_symbol',
    ];

    public function getStatusAttribute($val)
    {
        return self::STATUS[$this->attributes['status']] ?? '';
    }

    public function getQuantityAttribute($val)
    {
        $subscribeConf = SubscribeConf::query()->first();

        $multiple = $subscribeConf->multiple ?? 3;

        return bcmul($this->attributes['quantity'],$multiple,8);
    }
}
