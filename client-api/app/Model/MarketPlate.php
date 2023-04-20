<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\MarketPlate
 *
 * @property int $id
 * @property string $name 名称
 * @property int $created_at 创建时间
 * @property int $updated_at 更新时间
 * @property int $status 状态:0=禁用,1=启用
 * @method static \Illuminate\Database\Eloquent\Builder|MarketPlate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|MarketPlate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|MarketPlate query()
 * @method static \Illuminate\Database\Eloquent\Builder|MarketPlate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MarketPlate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MarketPlate whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MarketPlate whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MarketPlate whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class MarketPlate extends Model
{
    protected $table = 'market_plate';
    public $timestamps = false;

    protected $guarded =[];

}