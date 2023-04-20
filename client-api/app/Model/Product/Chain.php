<?php

declare (strict_types=1);

namespace App\Model\Product;

use Hyperf\DbConnection\Model\Model;

/**
 * @property int $id
 * @property int $chain_id
 * @property string $name
 * @property string $image
 * @property string $contract
 * @property string $trade_fee
 * @property string $application_ids
 * @property int $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Chain extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'chain';
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
    protected $casts = ['id' => 'integer', 'status' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];


    static function getList()
    {
        return self::select('id', 'name', 'picture')->where('status', 1)->get();
    }

    public function coin()
    {
        return $this->hasMany(Coin::class);
    }
}