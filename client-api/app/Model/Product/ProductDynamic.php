<?php

declare (strict_types=1);

namespace App\Model\Product;

use Hyperf\DbConnection\Model\Model;

/**
 * @property int $id
 * @property int $product_id
 * @property int $event
 * @property string $comefrom
 * @property string $reach
 * @property int $coin_id
 * @property string $price
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ProductDynamic extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'product_dynamics';
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
    protected $casts = [
        'id' => 'integer', 'product_id' => 'integer', 'event' => 'integer', 'coin_id' => 'integer',
        'created_at' => 'datetime', 'updated_at' => 'datetime'
    ];

    public function coin()
    {
        return $this->belongsTo(Coin::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}