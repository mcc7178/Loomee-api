<?php

declare(strict_types=1);

namespace App\Controller\Loomee;

use App\Controller\AbstractController;
use App\Model\Loomee\CollectionCategory;
use App\Model\Loomee\CollectionTradeTotal;
use App\Constants\StatusCode;
use App\Model\Loomee\Collection;
use App\Model\Loomee\Chain;
use Hyperf\DbConnection\Db;

class CollecTradeController extends AbstractController
{
    
    /**
     * 合集分类列表
     *
     * @return void
     */
    public function index()
    {
        $postData = $this->request->all();
        
        $cateList = (new CollectionCategory())->cacheDataList(['name','id']);
        $chainList = (new Chain())->cacheDataList(['name','id']);

        $tradeModel = new CollectionTradeTotal();
        $tradeModel->setModelCacheKey('index',json_encode($postData));

        $data = $tradeModel->getCache();
        if(empty($data))
        {
            $tradeList = $tradeModel->select('*', Db::raw('sum(achievement) as total_achievement'));

            $collection_select = [];
            if(array_key_exists('collection_name',$postData) && $postData['collection_name'] != '')
            {
                $where_collection_id = Collection::query()->where('name',$postData['collection_name'])->value('id');
                if($where_collection_id)    $collection_select = [$where_collection_id];
            }
            else
            {
                $chainSel = [];
                $cateSel = [];
                if(array_key_exists('chain_id',$postData) && $postData['chain_id'] != '')
                {
                    $chainSel = Collection::query()->where('chain_id',$postData['chain_id'])->pluck('id')->toArray();
                }

                if(array_key_exists('cate_id',$postData) && $postData['cate_id'] != '')
                {
                    $cateSel = Collection::query()->where('cate_id',$postData['cate_id'])->pluck('id')->toArray();
                }

                if(!empty($chainSel) && !empty($cateSel))
                {
                    $collection_select = array_intersect($chainSel, $cateSel);
                    $collection_select = array_values($collection_select);
                }
                elseif(!empty($chainSel))
                {
                    $collection_select = $chainSel;
                }
                elseif(!empty($cateSel))
                {
                    $collection_select = $cateSel;
                }
            }
            
            if(!empty($collection_select))    $tradeList = $tradeList->whereIn('collection_id',$collection_select);

            $data = $tradeList->groupBy('collection_id')
                ->orderBy('id','desc')
                ->paginate(15);

            foreach($data as $k=>&$v)
            {
                $collec =  Collection::query()->find($v->collection_id);
                if(!empty($collec))
                {
                    $v->collection_name  = $collec['name'] ?? '';
                    $v->chain_name       = $chainList[$collec['chain_id']] ?? '';
                    $v->cate_name        = $cateList[$collec['cate_id']] ?? '';
                }
                else
                {
                    $v->collection_name  = '';
                    $v->chain_name       = '';
                    $v->cate_name        = '';
                }
            }
            
            $tradeModel->synCache($data,900);
        }

        return $this->success([
            'list' => $data
        ]);
    }
    
   





}