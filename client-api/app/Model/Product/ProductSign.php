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
class ProductSign extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'product_sign';
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

    public static function getOneByWhere($where)
    {
        return self::where($where)->first();
    }
}