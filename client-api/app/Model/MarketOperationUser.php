<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\MarketOperationUser
 *
 * @property int $userid
 * @property string $account 手机号或者邮箱，手机号优先存储
 * @property int $op_id 操盘id
 * @property string|null $true_name 真实姓名
 * @property int|null $created_time
 * @method static \Illuminate\Database\Eloquent\Builder|MarketOperationUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|MarketOperationUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|MarketOperationUser query()
 * @method static \Illuminate\Database\Eloquent\Builder|MarketOperationUser whereAccount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MarketOperationUser whereCreatedTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MarketOperationUser whereOpId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MarketOperationUser whereTrueName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MarketOperationUser whereUserid($value)
 * @mixin \Eloquent
 */
class MarketOperationUser extends Model
{
    protected $table = 'market_operation_user';
    public $timestamps = false;


}