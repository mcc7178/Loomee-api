<?php
namespace App\Model;

use Hyperf\DbConnection\Model\Model;

/**
 * App\Models\UserRechargeAddressReserved
 *
 * @property int $id
 * @property string $address
 * @property string $symbol
 * @property int|null $created_time
 * @property string|null $tag
 * @property int $chain_id
 * @property int|null $status 是否占用
 */
class UserRechargeAddressReserved extends Model
{
    protected $table = 'user_recharge_address_reserved';
    public $timestamps = false;

    protected $guarded =[];
}