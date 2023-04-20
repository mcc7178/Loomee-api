<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\CoinsType
 *
 * @property int $id
 * @property string $name 名称
 * @property string $type 类型
 * @method static \Illuminate\Database\Eloquent\Builder|CoinsType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CoinsType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CoinsType query()
 * @method static \Illuminate\Database\Eloquent\Builder|CoinsType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CoinsType whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CoinsType whereType($value)
 * @mixin \Eloquent
 */
class CoinsType extends Model
{
    protected $table = 'coins_type';
    public $timestamps = false;

    protected $guarded =[];
}