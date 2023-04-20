<?php

namespace App\Model\Product;

use App\Model\Auth\Member;
use App\Model\Collection\Collection;
use App\Model\Model;
use App\Pool\Redis;

class ProductOffer extends Model
{
    protected $table = 'product_offer';
    protected $guarded = [];

    protected $dates = [
        'created_at',
        'updated_at',
        'expired_at',
    ];

    protected static $statusDesc = [
        1 => '已报价',
        2 => '已接受',
        3 => '已取消',
    ];

    /**
     * 获取产品报价列表数据
     * @param $product_id
     * @param $status
     * @param $chain_id
     * @param $address
     * @param $page
     * @param $size
     * @return array
     */
    public static function getList($product_id, $status, $chain_id, $address, $page = 1, $size = 20)
    {
        $model = self::query()->with([
            'product' => function ($query) {
                $query->with(['owner_id' => function ($subquery) {
                    $subquery->select(['id', 'address', 'username']);
                }])->select(['id', 'name', 'picture', 'animation_url', 'collection_id', 'coin_id', 'owner_id']);
            },
            'chain',
            'collection' => function ($query) {
                $query->select(['id', 'name', 'logo']);
            },
            'sign',
            'fromuser' => function ($query) {
                $query->select(['id', 'username', 'address']);
            }
        ])
            ->when($product_id, function ($query) use ($product_id) {
                $query->where('product_id', $product_id);
            })->when($status, function ($query) use ($status) {
                $query->whereIn('status', is_array($status) ? $status : [$status]);
            })->when($address, function ($query) use ($address) {
                $query->where('from', $address);
            })->when($chain_id, function ($query) use ($chain_id) {
                $query->where('chain_id', $chain_id);
            })->orderByRaw("created_at desc,expired_at asc");
        $count = $model->count();
        $offset = ($page - 1) * $size;
        $redis = Redis::getInstance();
        $eth_price = $redis->hGet('binance_price', $chain_id == 1 ? 'ETHUSDT' : 'BNBUSDT');
        $list = $model->offset($offset)->limit($size)->get()->toArray();
        foreach ($list as &$item) {
            $item['usdt_price'] = rtrim(bcmul(sprintf('%.8f', $item['amount']), (string)$eth_price, 8), '0');
            $item['floor_price'] = $redis->hGet('floor_price', "collection_{$item['collection_id']}");
            $item['product']['picture'] = !empty($item['product']['picture']) ? env('API_URL') . $item['product']['picture'] : '';
            $item['product']['animation_url'] = !empty($item['product']['animation_url']) ? env('API_URL') . $item['product']['animation_url'] : '';
            $item['product']['picture'] = $item['type'] == 2 ? Collection::query()->where('id', $item['collection_id'])->value('logo') ?? '' : $item['product']['picture'];
        }
        return [
            'count' => $count,
            'list' => $list
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function collection()
    {
        return $this->belongsTo(Collection::class);
    }

    public function chain()
    {
        return $this->belongsTo(Chain::class, 'chain_id');
    }

    public function sign()
    {
        return $this->hasOne(ProductSign::class, 'source_id');
    }

    public function fromuser()
    {
        return $this->belongsTo(Member::class, 'from', 'address');
    }
}