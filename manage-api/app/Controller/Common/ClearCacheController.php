<?php

declare(strict_types=1);

namespace App\Controller\Common;

use App\Controller\AbstractController;
use App\Model\Loomee\Chain;
use App\Model\Loomee\Coin;
use App\Model\Loomee\Collection;
use App\Model\Loomee\CollectionCategory;
use App\Model\Loomee\CollectionTradeTotal;
use App\Model\Loomee\KeywordExplain;
use App\Model\Loomee\Member;
use App\Model\Loomee\MemberFollow;
use App\Model\Loomee\Platform;
use App\Model\Loomee\Product;
use App\Model\Loomee\ProductAttribute;
use App\Model\Loomee\ProductDynamics;


class ClearCacheController extends AbstractController
{

    protected $cache_model = [
        'chain'                 => Chain::class,
        'coin'                  => Coin::class,
        'collection'            => Collection::class,
        'collectionCategory'    => CollectionCategory::class,
        'collectionTradeTotal'  => CollectionTradeTotal::class,
        'keywordExplain'        => KeywordExplain::class,
        'member'                => Member::class,
        'memberFollow'          => MemberFollow::class,
        'platform'              => Platform::class,
        'product'               => Product::class,
        'productAttribute'      => ProductAttribute::class,
        'productDynamics'       => ProductDynamics::class,
    ];



    /**
     * 清理缓存
     *
     * @return void
     */
    public function clear()
    {
        $all = $this->request->all();

        $model_name = $all['model'] ?? '';

        if($model_name == 'all')
        {
            foreach($this->cache_model as $k=>$v)
            {
                $model = new $v;
                $model->delCache();
            }
             
            return '已清理';
        }
        else
        {
            if(array_key_exists($model_name,$this->cache_model))
            {
                $model = new $this->cache_model[$model_name];
                $model->delCache();

                return '已清理';
            }
            else
            {
                return '暂无可清理的缓存';
            }
        }
    }

    







}
