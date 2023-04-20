<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\MarketOperation
 *
 * @property int $id
 * @property string|null $fee 手续费百分比
 * @property string|null $sms 手机短信
 * @property string|null $title 标题
 * @property string|null $description 说明
 * @property int|null $status 1：启用 0：禁用
 * @property string|null $market 市场
 * @method static \Illuminate\Database\Eloquent\Builder|MarketOperation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|MarketOperation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|MarketOperation query()
 * @method static \Illuminate\Database\Eloquent\Builder|MarketOperation whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MarketOperation whereFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MarketOperation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MarketOperation whereMarket($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MarketOperation whereSms($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MarketOperation whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MarketOperation whereTitle($value)
 * @mixin \Eloquent
 */
class MarketOperation extends Model
{
    protected $table = 'market_operation';
    public $timestamps = false;

    protected $guarded =[];

}