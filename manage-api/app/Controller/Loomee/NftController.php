<?php

declare(strict_types=1);

namespace App\Controller\Loomee;

use App\Controller\AbstractController;
use App\Model\Loomee\CollectionCategory;
use App\Model\Loomee\Product;
use App\Model\Loomee\ProductAttribute;
use App\Model\Loomee\ProductDynamics;
use App\Model\Loomee\KeywordExplain;
use App\Model\Loomee\Collection;
use App\Model\Loomee\Chain;
use App\Process\NftTaskProcess;

use App\Foundation\Facades\Log;

class NftController extends AbstractController
{
    
    /**
     * 列表
     *
     * @return void
     */
    public function index()
    {
        $postData = $this->request->all();
        
        $cateList = (new CollectionCategory())->cacheDataList(['name','id']);
        $chainList = (new Chain())->cacheDataList(['name','id']);
        $collecList = (new Collection())->cacheDataList(['name','id']);

        $productModel = new Product();
        $productModel->setModelCacheKey('index',json_encode($postData));

        $data = $productModel->getCache();
        if(empty($data))
        {
            $productList = $productModel;
            if(array_key_exists('product_name',$postData) && $postData['product_name'] != '')
                $productList = $productList->where('name','like',"%{$postData['product_name']}%");
            
            if(array_key_exists('collection_name',$postData) && $postData['collection_name'] != '')
            {
                $where_cid = Collection::query()->where('name','like',"%{$postData['collection_name']}%")->pluck('id')->toArray();
                if(!empty($where_cid))    $productList = $productList->whereIn('collection_id',$where_cid);
            }  
            
            if(array_key_exists('chain_id',$postData) && (int)$postData['chain_id'] != 0)
                $productList = $productList->where('chain_id',(int)$postData['chain_id']);

            if(array_key_exists('cate_id',$postData) && (int)$postData['cate_id'] != 0)
                $productList = $productList->where('cate_id',(int)$postData['cate_id']);
            
            if(array_key_exists('sell_type',$postData) && $postData['sell_type'] != '')
                $productList = $productList->where('sell_type',(int)$postData['sell_type']);

            $data = $productList->orderBy('id','desc')
                ->paginate(15);
    
            foreach($data as $k=>&$v)
            {
                $v->collection_name  = $collecList[$v->collection_id] ?? '';
                $v->cate_name        = $cateList[$v->cate_id] ?? '';
                $v->chain_name       = $chainList[$v->chain_id] ?? '';
            }

            $productModel->synCache($data,300);
        }

        return $this->success([
            'list' => $data
        ]);
    }
    
    


    /**
     * 查看属性
     * 
     * @param integer $id
     * @return void
     */
    public function element(int $id)
    {
        $proAttrModel = new ProductAttribute();
        $proAttrModel->setModelCacheKey('element','element_'.$id);

        $data = $proAttrModel->getCache();
        if(empty($data))
        {
            $data = $proAttrModel->where('product_id',$id)->get()->toArray();
            $proAttrModel->synCache($data,300);
        }

        return $this->success([
            'list' => $data
        ]);
    }



    /**
     * nft上架
     * @return void
     */
    public function shelves()
    {
        $postData = $this->request->all();

        $params = [
            'chain_id'      => $postData['chain_id'] ??'',
            'contract'      => $postData['contract'] ??'',
            'cate_id'       => $postData['cate_id'] ??'',
            'collection_id' => $postData['collection_id'] ??'',
            'owner'         => $postData['owner'] ??'',
        ];

        //配置验证
        $rules = [
            'chain_id'      => 'required',
            'contract'      => 'required',
            'cate_id'       => 'required',
            'collection_id' => 'required',
        ];
        $message = [
            'chain_id.required'         => '请选择公链',
            'contract.required'         => '合约地址不能为空',
            'cate_id.required'          => '请选择藏品分类',
            'collection_id.required'    => '请选择合集',
        ];

        $this->verifyParams($params, $rules, $message);

        $task = new NftTaskProcess();
        $task->nftReptile($params);

        return $this->successByMessage("任务已添加");
    }


    /**
     * 获取事件分类
     *
     * @return void
     */
    public function getEvent()
    {
        $kyModel = new KeywordExplain();
        $where = [
            'sign_table' => 'product_dynamics',
            'sign_field' => 'event',
        ];
        $kyModel->setModelCacheKey('getEvent',json_encode($where));

        $data = $kyModel->getCache();
        if(empty($data))
        {
            $value = $kyModel->where($where)->pluck('value');

            $list = json_decode($value[0]??'',true);
        
            $data = [];
            foreach($list as $k=>$v)
            {
                $data[] = [
                    'id'=>$k,
                    'name'=>$v
                ];
            }

            $kyModel->synCache($data,300);
        }

        return $this->success([
            'list' => $data
        ]);  
    }
 
    /**
     * nft动态
     *
     * @return void
     */
    public function dynamics()
    {
        $postData = $this->request->all();
        
        $chainList = (new Chain())->cacheDataList(['name','id']);
        $collecList = (new Collection())->cacheDataList(['name','id']);

        $dynamicsModel = new ProductDynamics();
        $dynamicsModel->setModelCacheKey('dynamics',json_encode($postData));

        $data = $dynamicsModel->getCache();
        $where_sign = false;
        if(empty($data))
        {
            $dynamicsList = $dynamicsModel;
            if(array_key_exists('event_id',$postData) && (int)$postData['event_id'] != 0)
            {
                $dynamicsList = $dynamicsList->where('event',(int)$postData['event_id']);
            }

            $product_in = [];
            if(array_key_exists('product_name',$postData) && $postData['product_name'] != '')
            {
                $product_in[] = Product::query()->where('name','like',"%{$postData['product_name']}%")->pluck('id')->toArray();
                $where_sign = true;
            }
            
            if(array_key_exists('collection_name',$postData) && $postData['collection_name'] != '')
            {
                $where_cid = Collection::query()->where('name','like',"%{$postData['collection_name']}%")->pluck('id')->toArray();
                if(!empty($where_cid))    
                    $product_in[] = Product::query()->whereIn('collection_id',$where_cid)->pluck('id')->toArray();
                else
                    $product_in[] = [];

                $where_sign = true;
            }
            
            if(array_key_exists('chain_id',$postData) && (int)$postData['chain_id'] != 0)
            {
                $product_in[] = Product::query()->where('chain_id',(int)$postData['chain_id'])->pluck('id')->toArray();
                $where_sign = true;
            }

            $where_arr = [];
            foreach($product_in as $kp=>$vp)
            {
                if(empty($vp))
                {
                    $where_arr = [];
                    break;
                }
                else
                {
                    if(empty($where_arr))
                    {
                        $where_arr = $vp;
                    }
                    else
                    {
                        $where_arr = array_intersect($vp, $where_arr);
                        $where_arr = array_values($where_arr);
                    }
                }
            }
            
            if($where_sign && empty($where_arr))    
            {
                $data = $dynamicsList->where('id',0)->orderBy('id','desc')
                    ->paginate(15);
            }
            else
            {
                if(!empty($where_arr)) $dynamicsList = $dynamicsList->whereIn('product_id',$where_arr);

                $data = $dynamicsList->orderBy('id','desc')
                    ->paginate(15);
            }

            foreach($data as $k=>&$v)
            {
                $product =  Product::query()->find($v->product_id);
                $v->product_name  = $product['name'] ?? '';
                $v->collection_name  = $collecList[$product['collection_id']] ?? '';
                $v->chain_name       = $chainList[$product['chain_id']] ?? '';
            }

            $dynamicsModel->synCache($data,300);
        }
        
        return $this->success([
            'list' => $data
        ]);
    }
    



















}