<?php

declare (strict_types=1);

namespace App\Model\Product;

use App\Constants\RedisKey;
use App\Foundation\Facades\Log;
use App\Utils\Redis;
use Hyperf\DbConnection\Db;
use Hyperf\DbConnection\Model\Model;

/**
 * @property int $id
 * @property int $product_id
 * @property string $titile
 * @property string $value
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ProductAttribute extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'product_attribute';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = ['id' => 'integer', 'product_id' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    public static function getAttributeGroupList($collection_id = 0)
    {
        $redis = Redis::getInstance();
        if (!$titles = $redis->sMembers(RedisKey::COLLECTION_ATTRIBUTE . $collection_id)) {
            $titles = self::select(Db::raw("distinct(title)"))
                ->leftJoin('product as p', 'product_attribute.product_id', '=', 'p.id')
                ->leftJoin('collection as c', 'p.collection_id', '=', 'c.id')
                ->where('c.id', $collection_id)
                ->get()
                ->toArray();
            $titles = array_filter(array_column($titles, 'title'));
            Log::codeDebug()->info(RedisKey::COLLECTION_ATTRIBUTE . $collection_id . json_encode($titles));
            $redis->sAddArray(RedisKey::COLLECTION_ATTRIBUTE . $collection_id, $titles);
        }
        $result = [];
        if ($titles) {
            foreach ($titles as $title) {
                $itemKey = RedisKey::COLLECTION_ATTRIBUTE . $collection_id . '_' . str_replace(' ', '_', $title);
                $values = $redis->get($itemKey);
                if (!$values) {
                    $values = self::select(Db::raw("distinct(value) as value"))
                        ->leftJoin('product as p', 'product_attribute.product_id', '=', 'p.id')
                        ->leftJoin('collection as c', 'p.collection_id', '=', 'c.id')
                        ->where('c.id', $collection_id)
                        ->where('title', $title)
                        ->get()
                        ->toArray();
                    $temp = [];
                    foreach ($values as $value) {
                        $num = ProductAttribute::query()->selectRaw("count(distinct(product_id)) as num")->where('value', $value['value'])->first();
                        $temp[] = [
                            'value' => $value['value'],
                            'num' => $num->num ?? 0
                        ];
                    }
                    $values = $temp;
                    $redis->set($itemKey, json_encode($values));
                } else {
                    $values = json_decode($values, true);
                }
                $result[] = [
                    'name' => $title,
                    'count' => 1,
                    'values' => $values
                ];
            }
        }
        return $result;
    }
}