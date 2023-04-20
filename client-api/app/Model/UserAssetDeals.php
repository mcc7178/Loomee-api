<?php
namespace App\Model;

use App\Traits\HasCompositePrimaryKey;
use Hyperf\DbConnection\Model\Model;
/**
 * App\Models\UserAssetDeals
 *
 * @property int $userid
 * @property string $coin 币种
 * @property string $quantity 可用余额
 * @property string $freeze 冻结余额
 * @property string $invite_power 推荐算力
 * @property string $quantity_power 持币算力
 * @property-read \App\Model\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|UserAssetDeals newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserAssetDeals newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserAssetDeals query()
 * @method static \Illuminate\Database\Eloquent\Builder|UserAssetDeals whereCoin($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAssetDeals whereFreeze($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAssetDeals whereInvitePower($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAssetDeals whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAssetDeals whereQuantityPower($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAssetDeals whereUserid($value)
 * @mixin \Eloquent
 * @property int $id
 * @method static \Illuminate\Database\Eloquent\Builder|UserAssetDeals whereId($value)
 */
class UserAssetDeals extends Model
{
//    use HasCompositePrimaryKey;

    protected $table = 'user_asset_recharges';
    public $timestamps = false;

    protected $guarded =[];
//    protected $primaryKey = 'userid';
//    public $incrementing = false;
//    protected $keyType = 'string';
    public function user()
    {
	    return $this->belongsTo('App\Models\User', 'userid');
	}

    /*public function getQuantityAttribute($value)
    {
        return bcadd($value, 0, 2);
	}*/
}
