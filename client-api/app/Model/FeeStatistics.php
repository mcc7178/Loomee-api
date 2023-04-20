<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;


class FeeStatistics extends Model
{
    protected $table = 'fee_statistics';
    public $timestamps = false;
    public $dateFormat = 'U';

    protected $fillable = [
        'num',
        'type',
        'fund_quantity',
        'insurance_quantity',
        'platform_quantity',
        'relation_id',
        'coin',
    ];

    const TYPE_WITHDRAW = 'withdraw';
    const TYPE_TRANSFER = 'transfer';
    const TYPE_FDD      = 'fee';

    const TYPE = [
        self::TYPE_WITHDRAW => '提现',
        self::TYPE_TRANSFER => '互转',
        self::TYPE_FDD      => '交易手续费'
    ];

    protected $casts = [
        'create_time' => 'datetime'
    ];

    public function getCreatedAtAttribute($val)
    {
        return is_numeric($val) ? date('Y-m-d H:i:s', $val) : $val;
    }


}
