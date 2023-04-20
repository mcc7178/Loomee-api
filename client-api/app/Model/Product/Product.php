<?php

declare (strict_types=1);

namespace App\Model\Product;

use App\Constants\Common;
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
class Product extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'product';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer', 'collection_id' => 'integer', 'cate_id' => 'integer', 'owner_id' => 'integer',
        'sales_nums' => 'integer', 'sell_type' => 'integer', 'chain_id' => 'integer', 'coin_id' => 'integer',
        'updated_at' => 'datetime', 'status' => 'integer'
    ];

    protected $modelOrderBy = [
        1 => ['orderField' => 'product.shelves_time', 'orderType' => 'desc'],   //最近上架1
        2 => ['orderField' => 'product.created_at', 'orderType' => 'desc'],     //最新创建
        3 => ['orderField' => 'product.sold_time', 'orderType' => 'desc'],      //最近卖出
        4 => ['orderField' => 'product.price', 'orderType' => 'asc'],           //价格升序
        5 => ['orderField' => 'product.price', 'orderType' => 'desc'],          //价格降序
    ];

    public function getList($query, $field = '*')
    {
        if (empty($query['orderBy']) || !in_array($query['orderBy'], array_keys($this->modelOrderBy))) {
            $query['orderBy'] = 2;
        }
        $orderField = $this->modelOrderBy[$query['orderBy']]['orderField'];
        $orderType = $this->modelOrderBy[$query['orderBy']]['orderType'];

        if ($field == '*') {
            $field = ['product.*'];
        } elseif (is_string($field)) {
            $field = array_filter(explode(',', $field));
        }
        $size = (int)($query['size'] ?? 20);
        $page = (int)($query['page'] ?? 1);
        $model = self::query();
        if (!empty($query['id'])) {
            $model->where('id', $query['id']);
        }
        if (!empty($query['name'])) {
            $name = $query['name'];
            if (strpos($name, '0x') !== false) {
                $model->where('product.contract_url', $name);
            } else {
                $model->where('product.name', 'like', '%' . $name . '%');
            }
        }
        if (!empty($query['chain'])) {
            $chain = array_filter(explode(',', $query['chain']));
            $model->whereIn('chain_id', $chain);
        }
        if (!empty($query['coin_id']) && !empty($query['price'])) {
            $coin_id = array_filter(explode(',', $query['coin_id']));
            $price = explode(',', rtrim($query['price'], ','));
            if (count($price) == 2) {
                if ($price[0] && $price[1]) {
                    $model->whereBetween('price', $price);
                } elseif ($price[0] && !$price[1]) {
                    $model->where('price', '>=', $price[0]);
                } elseif (!$price[0] && $price[1]) {
                    $model->where('price', '<=', $price[1]);
                }
            }
            $model->whereIn('coin_id', $coin_id);
        }
        if (isset($query['sell_type']) && $query['sell_type'] != '') {
            $model->where('sell_type', $query['sell_type']);
        }
        if (!empty($query['collection_id'])) {
            $collection_id = array_filter(explode(',', $query['collection_id']));
            $model->whereIn('collection_id', $collection_id);
        }
        if (!empty($query['cate_id'])) {
            $cate_id = array_filter(explode(',', $query['cate_id']));
            $model->whereIn('cate_id', $cate_id);
        }
        if (isset($query['status']) && $query['status'] != '') {
            $model->where('product.status', $query['status']);
        }
        if (!empty($query['attribute'])) {
            $titleSql = '';
            $values = [];
            foreach ($query['attribute'] as $k => $item) {
                $item = json_decode($item, true);
                $titleSql .= ($k == 0 ? '' : " OR ") . "`title` = '" . $item['title'] . "'";
                $values = array_merge($item['value'], $values);
            }
            $prdList = Db::select("select `product_id` from product_attribute where ($titleSql) and `value` in ('" . implode("','", $values) . "')");
            if ($prdList) {
                $model->whereIn('product.id', array_unique(array_column($prdList, 'product_id')));
            } else {
                $model->where('product.id', -1);
            }
        }
        if (!empty($query['own'])) {
            $model->where('owner', $query['address']);
        }
        if (!empty($query['follow'])) {
            $model->leftJoin('member_follow', 'member_follow.product_id', '=', 'product.id')->where('member_follow.address', $query['address'])->where('member_follow.status', 1);
        }

        //除 我的NFT 列表之外，其他的不显示已下架的合集NFT
        if (empty($query['address'])) {
            $subList = Collection::query()->where('status', 1)->select('id')->get()->toArray();
            $ids = array_column($subList, 'id');
            $model->whereIn('collection_id', $ids);
        }

        $model = self::getWithCondition($model);
        if (in_array($query['orderBy'], [1, 4, 5])) {
            $model->orderBy('product.status', 'desc');
            if ($orderType == 'asc') {
                $model->orderBy(Db::raw("(CASE WHEN `product`.`price` > 0 THEN 1 WHEN `product`.`price` = 0 THEN 2 END)"));
            } else {
                $model->orderBy($orderField, $orderType);
            }
        } else {
            $model->orderBy($orderField, $orderType);
        }
        $total = $model->select($field)->count();
        $list = $model->offset(($page - 1) * $size)->limit($size)->get()->toArray();
        if ($list) {
            foreach ($list as &$item) {
                $item['sign'] = ProductSign::where('product_id', $item['id'])->orderByDesc('id')->first();
            }
        }
        return [
            'total' => $total,
            'list' => $list
        ];
    }

    private static function getWithCondition($model, $getOne = false)
    {
        $model->with([
            'category' => function ($query) {
                $query->select(['id', 'name', 'icon']);
            },
            'collection' => function ($query) {
                $query->select(['id', 'name', 'copyright_fee', 'logo']);
            },
            'coin' => function ($query) {
                $query->select(['id', 'name', 'image']);
            },
            'chain' => function ($query) {
                $query->with('coin')->where('status', 1);
            },
            'sign' => function ($query) {
                $query->orderBydesc('id')->first();
            }
        ]);
        if ($getOne) {
            $model->with([
                'attribute' => function ($query) {
                    $query->select(['id', 'product_id', 'title', 'value']);
                },
                'owner_id' => function ($query) {
                    $query->select(['id', 'nickname', 'username']);
                },
                'follow' => function ($query) {
                    $query->select('product_id', Db::raw('count(*) as num'))->where('status', 1);
                },
                'dynamic' => function ($query) {
                    $query->with([
                        'coin' => function ($query) {
                            $query->select(['id', 'name', 'image']);
                        }
                    ])->orderByDesc('updated_at');
                },
            ]);
        }
        return $model;
    }

    public static function detail(int $id, $user_id)
    {
        $model = self::where('id', $id);
        $detail = self::getWithCondition($model, 1)->firstOrFail()->toArray();
        $detail['last_price'] = 0;
        $detail['button_type'] = 'none';

        //未上架不能购买，价格显示上次成交的价格，没上次成交价格显示 --
        if ($detail['status'] == 0) {
            $price = ProductDynamic::query()->where('product_id', $detail['id'])->orderByDesc('updated_at')->value('price') ?? 0;
            $detail['last_price'] = $price ?: '--';
            if (!empty($detail['owner_id']) && ($detail['owner_id']['id'] == $user_id)) {
                $detail['button_type'] = 'on-shelves';
            }
        } elseif ($detail['status'] == 1) {
            if (!empty($detail['owner_id']) && ($detail['owner_id']['id'] == $user_id)) {
                $detail['button_type'] = 'off-shelves';
            } else {
                $detail['button_type'] = 'buy';
            }
            $detail['last_price'] = $detail['price'];
        }
        $detail['sign'] = $detail['sign'][0] ?? [];
        $detail['follow_status'] = 0;
        if ($user_id) {
            $detail['follow_status'] = MemberFollow::query()->where(['member_id' => $user_id, 'product_id' => $id])->value('status') ?? 0;
        }
        if (strtolower(env('APP_ENV')) == 'dev') {
            $detail['url_prefix'] = Common::TEST_ETH_TRADE_URL;
        } else {
            $detail['url_prefix'] = Common::PRODUCT_ETH_TRADE_URL;
        }
        return $detail;
    }

    public static function getOneById($id, $userId)
    {
        return self::query()->where('id', $id)->where('owner_id', $userId)->first();
    }

    public static function getOneByWhere($where)
    {
        return self::where($where)->first();
    }

    /**
     * @return BelongsTo
     */
    public function collection()
    {
        return $this->belongsTo(Collection::class);
    }

    /**
     * @return BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(CollectionCategory::class, 'cate_id');
    }

    public function coin()
    {
        return $this->belongsTo(Coin::class);
    }

    public function dynamic()
    {
        return $this->hasMany(ProductDynamic::class);
    }

    public function attribute()
    {
        return $this->hasMany(ProductAttribute::class);
    }

    public function chain()
    {
        return $this->belongsTo(Chain::class);
    }

    public function owner_id()
    {
        return $this->belongsTo(Member::class, 'owner_id');
    }

    public function follow()
    {
        return $this->hasMany(MemberFollow::class);
    }

    public function sign()
    {
        return $this->hasMany(ProductSign::class);
    }

}