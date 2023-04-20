<?php

declare(strict_types=1);

namespace App\Model\Collection;

use App\Model\Model;
use App\Model\Product\Product;
use Hyperf\DbConnection\Db;

class CollectionTradeTotal extends Model
{

    protected $container;
    protected $table = 'collection_trade_total';
    protected $connection = 'default';

    protected $fillable = [];
    protected $casts = [];

    /**
     * Notes: 当天交易的合集排序
     * User: Deycecep
     * DateTime: 2022/4/18 17:30
     * @return mixed
     */
    static function getCollectionIdByTradeSort()
    {
        return CollectionTradeTotal::query()->where('date', date('Y-m-d'))->orderBy('achievement', 'desc')->pluck('collection_id');
    }

    /**
     * Notes: 最新一条记录
     * User: Deycecep
     * DateTime: 2022/4/19 9:43
     * @return mixed
     */
    static function getCollectionTradeLastRecord()
    {
        return CollectionTradeTotal::query()->orderBy('id', 'desc')->first();
    }

    public function collection()
    {
        return $this->belongsTo(Collection::class);
    }

    /**
     * 销量排行榜
     * @param $query
     * @param $page
     * @param $size
     * @return array
     */
    public static function rankingList($query, $page = 1, $size = 20)
    {
        $field = "ct.*,sum(ct.achievement) total,avg(avg_price) avg_price,min(min_price) min_price, sum(owners) owners,";
        $field .= "co.chain_id,co.picture collection_pic,co.name collection_name,ch.picture chain_pic,co.contract,co.logo";
        $sql = " from collection_trade_total ct left join collection as co on co.id = ct.collection_id left join chain as ch on ch.id = co.chain_id where 1";
        if (!empty($query['chain'])) {
            $sql .= " and co.chain_id = " . $query['chain'];
        }
        if (!empty($query['cate'])) {
            $sql .= " and co.cate_id = " . $query['cate'];
        }
        $end = date('Y-m-d');
        if (!empty($query['time'])) {
            $time = $query['time'];
            switch ($time) {
                case '24hours':
                    $start = date('Y-m-d');
                    break;
                case '7days':
                    $start = date('Y-m-d', strtotime('-6 days', strtotime($end)));
                    break;
                case '30days':
                    $start = date('Y-m-d', strtotime('-29 days', strtotime($end)));
                    break;
            }
            if (!empty($start)) {
                $sql .= " and ct.date between '$start' and '$end'";
            }
        }

        $offset = ($page - 1) * $size;
        $total = count(Db::select("select count(1) as count $sql group by ct.collection_id"));
        $list = Db::select("select $field $sql group by ct.collection_id order by sum(ct.achievement) desc limit $offset,{$size}");
        if ($list) {
            $temp = Product::query()->select(Db::raw("count(id) as num"), 'collection_id')
                ->whereIn('collection_id', array_column($list, 'collection_id'))->groupBy(['collection_id'])
                ->get()->keyBy('collection_id')->toArray();
            foreach ($list as $item) {
                $item->sales_nums = $temp[$item->collection_id]['num'] ?? 0;
                $item->avg_price = sprintf("%.4f", $item->avg_price);
            }
        }

        return [
            'total' => $total,
            'list' => $list
        ];
    }
}