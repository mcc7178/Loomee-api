<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\TradeLog
 *
 * @method static \Illuminate\Database\Eloquent\Builder|TradeLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TradeLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TradeLog query()
 * @mixin \Eloquent
 */
class TradeLog extends Model
{
    protected $table = 'trade_log';
    public $timestamps = false;

    protected $guarded =[];

    public function setTable($market)
    {
    	$this->table = preg_match('/^[a-z\d]+_[a-z\d]+$/', $market)?"trade_{$market}_log":false;
    	return $this;
    }

}