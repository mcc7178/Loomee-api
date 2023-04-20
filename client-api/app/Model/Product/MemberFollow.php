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
class MemberFollow extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'member_follow';
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


    static function getList()
    {
        return self::where('status', 1)->get();
    }

    /**
     * 关注或取消
     * @param $user_id
     * @param $address
     * @param $product_id
     * @return bool
     */
    public static function createOrupdate($user_id, $address, $product_id)
    {
        $exist = Product::find($product_id);
        if (!$exist) {
            return false;
        }
        $model = self::where(['product_id' => $product_id, 'member_id' => $user_id])->first();
        if (!$model) {
            $model = new self();
            $model->member_id = $user_id;
            $model->address = $address;
            $model->product_id = $product_id;
            $model->status = 1;
        } else {
            $model->status = $model->status ? 0 : 1;
        }
        return $model->save();
    }
}