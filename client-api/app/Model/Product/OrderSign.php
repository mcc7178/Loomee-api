<?php

declare (strict_types=1);

namespace App\Model\Product;

use App\Model\Auth\Member;
use App\Model\Collection\Collection;
use App\Model\Collection\CollectionCategory;
use Hyperf\Database\Model\Relations\BelongsTo;
use Hyperf\DbConnection\Model\Model;
use Hyperf\DbConnection\Db;

/**
 * @property int $id
 * @property string $name
 * @property int $collection_id
 * @property int $cate_id
 * @property string $contract_url
 * @property string $author
 * @property string $owner
 * @property int $owner_id
 * @property string $sales
 * @property int $sales_nums
 * @property string $coin_token
 * @property int $sell_type
 * @property int $chain_id
 * @property int $coin_id
 * @property string $price
 * @property string $created_at
 * @property \Carbon\Carbon $updated_at
 * @property string $deleted_at
 * @property string $sold_time
 * @property string $shelves_time
 * @property int $status
 */
class OrderSign extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'order_sign';

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
    protected $casts = [];

    public static function getOneById($id)
    {
        return self::query()->where('id', $id)->first();
    }


}