<?php

declare(strict_types=1);

namespace App\Model\Collection;

use App\Model\Model;
use App\Model\Product\Chain;
use App\Model\Product\Product;
use App\Model\Product\ProductAttribute;
use App\Model\Product\ProductDynamic;
use Hyperf\Contract\LengthAwarePaginatorInterface;
use Hyperf\DbConnection\Db;
use phpDocumentor\Reflection\Types\Mixed_;

class Collection extends Model
{

    protected $container;
    protected $table = 'collection';
    protected $connection = 'default';

    protected $fillable = ['element'];
    protected $casts = [
        'element' => 'array'
    ];

    /**
     * Notes:合集列表
     * User: Deycecep
     * DateTime: 2022/4/18 15:35
     * @param $name
     * @return mixed
     */
    static function getList($name)
    {
        /* $lastRecord = CollectionTradeTotal::getCollectionTradeLastRecord();
         $query = self::leftJoin('collection_trade_total', 'collection.id', '=', 'collection_trade_total.collection_id');
         if ($lastRecord) {
             $query->where('collection_trade_total.date', $lastRecord->date);
         }
         $query = $query->where('collection.status', 1)
             ->select('collection.*', 'collection_trade_total.achievement')
             ->orderBy('achievement', 'desc');
         if ($name)
             $query = $query->where('collection.name', 'like', '%' . $name . '%');*/
        return self::query()->where('status', 1)->orderByDesc('id')->select(['id', 'name', 'logo'])->get();
        //return $query->get();
    }

    /**
     * 合集动态
     * @param $collection_id
     * @param $event
     * @param $page
     * @param $size
     * @param $address
     * @return mixed
     */
    public static function dynamic($input)
    {
        $collection_id = $input['id'] ?? '';
        $event = $input['event'] ?? '';
        $page = (int)($input['page'] ?? 1);
        $size = (int)($input['size'] ?? 20);
        $address = $input['address'] ?? '';
        $attribute = $input['attribute'] ?? '';
        $model = ProductDynamic::leftJoin("product", "product_dynamics.product_id", '=', 'product.id')
            ->select(['product_dynamics.*', 'product.name']);
        if ($collection_id) {
            $model->where('product.collection_id', $collection_id);
        }
        if ($event) {
            $model->where('product_dynamics.event', $event);
        }
        if ($address) {
            $model->where("product.owner", $address);
        }
        if (!empty($input['collection_id'])) {
            $collection_id = array_filter(explode(',', $input['collection_id']));
            $model->whereIn('product.collection_id', $collection_id);
        }
        if (!empty($input['cate_id'])) {
            $cate_id = array_filter(explode(',', $input['cate_id']));
            $model->whereIn('product.cate_id', $cate_id);
        }
        if (!empty($input['chain'])) {
            $chainids = array_filter(explode(',', $input['chain']));
            $model->where('product.chain_id', 'in', $chainids);
        }
        if ($attribute) {
            $titleSql  = '';
            $values = [];
            foreach ($attribute as $k => $item) {
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

        $offset = ($page - 1) * $size;
        $total = $model->count();
        $list = $model->orderByDesc('updated_at')->offset($offset)->limit($size)->get()->toArray();
        return [
            'total' => $total,
            'data' => $list
        ];
    }

    /**
     * 合集详情
     * @param $id
     * @return mixed
     */
    public static function info($id)
    {
        $product_data = Product::query()->where('collection_id', $id)->select('id', 'owner')->get()->toArray();
        $collection_trade_data = CollectionTradeTotal::query()
            ->where('collection_id', $id)->select('achievement', 'min_price')->get()->toArray();
        return array_merge(self::with([
            'chain' => function ($query) {
                $query->select(['id', 'picture']);
            }
        ])->findOrFail($id)->toArray(), [
            'nft_number' => $product_data ? count($product_data) : 0,
            'owner_number' => $product_data ? count(array_unique(array_column($product_data, 'owner'))) : 0,
            'total_price' => $collection_trade_data ? sprintf("%.4f", array_sum(array_column($collection_trade_data, 'achievement'))) : 0,
            'min_price' => $collection_trade_data ? min(array_column($collection_trade_data, 'min_price')) : 0,
            'attributes' => ProductAttribute::getAttributeGroupList($id)
        ]);
    }

    /**
     * 合集列表
     * @param $chain_id
     * @param $cate_id
     * @param $page
     * @param $size
     * @return mixed
     */
    public static function list($chain_id, $cate_id, $page = 1, $size = 20)
    {
        $model = self::where('status', 1);
        if (!empty($chain_id)) {
            $model->where('chain_id', $chain_id);
        }
        if (!empty($cate_id)) {
            $model->where('cate_id', $cate_id);
        }
        $offset = ($page - 1) * $size;
        $total = $model->count();
        $list = $model->orderByDesc('shelves_at')->offset($offset)->limit($size)->get()->toArray();
        return [
            'total' => $total,
            'data' => $list
        ];
    }

    /**
     * Notes: 根据条件查单个合集
     * User: Deycecep
     * DateTime: 2022/4/22 15:16
     * @param $where
     * @return mixed
     */
    public static function getOneByWhere($where)
    {
        return self::where($where)->first();
    }

    public function chain()
    {
        return $this->belongsTo(Chain::class);
    }
}