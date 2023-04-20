<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;


/**
 * App\Models\Trade
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Trade newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Trade newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Trade query()
 * @mixin \Eloquent
 */
class Trade extends Model
{
    protected $table = '';
    public $timestamps = false;

    protected $guarded =[];

    public function setTable($market)
    {
    	$this->table = preg_match('/^[a-z\d]+_[a-z\d]+$/', $market)?"trade_{$market}":false;
    	return $this;
    }


    public static function getQueueName($market)
    {
    	return 'trade_queue:'.strtolower($market);
    }


    public function getMultiMarketList(Array $markets, $userId, $endTime, $pageSize=8)
    {
        $subSqls = [];
        $fields = 'id,type,quantity,price,volume,created_time,market,status,trade_type,unsettled';
        foreach($markets as $market)
        {

            $subSqls[] = sprintf('(SELECT %s FROM trade_%s WHERE user_id=%d AND created_time<%d ORDER BY created_time DESC LIMIT %d)', $fields, $market, $userId, $endTime, $pageSize);
        }

        $subSql = implode(' union ', $subSqls);

        $sql = sprintf("SELECT * FROM (%s) t ORDER BY created_time DESC, id DESC LIMIT %d", $subSql, $pageSize);

        $result = \DB::select($sql, []);

        return $result;
    }
}