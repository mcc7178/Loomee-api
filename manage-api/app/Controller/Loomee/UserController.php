<?php

declare(strict_types=1);

namespace App\Controller\Loomee;

use App\Controller\AbstractController;
use App\Model\Loomee\Member;
use App\Model\Loomee\MemberFollow;
use App\Model\Loomee\Collection;
use App\Model\Loomee\Chain;
use App\Model\Loomee\Product;
use App\Constants\StatusCode;

class UserController extends AbstractController
{
    
    /**
     * 用户列表
     *
     * @return void
     */
    public function index()
    {
        $postData = $this->request->all();

        $members = new Member();
        $members->setModelCacheKey('index',json_encode($postData));
        $permissionList = $members->getList();

        return $this->success([
            'list' => $permissionList,
        ]);
    }

    /**
     * 修改用户状态
     *
     * @param integer $id
     * @return void
     */
    public function updateStatus(int $id)
    {
        $user = Member::query()->find($id);
    
        if(empty($user))        $this->throwExp(StatusCode::USER_NOTEXISTS, '用户不存在');;
        // $permissionList = $userinfo->get()->toArray();
        $status = 0;
        if($user->status == 0)  $status = 1;
        $user->status = $status;
        $user->save();
        
        return $this->successByMessage('修改成功');
    }


    public function follow()
    {
        $postData = $this->request->all();
        
        $chainList = (new Chain())->cacheDataList(['name','id']);
        $collecList = (new Collection())->cacheDataList(['name','id']);

        $followModel = new MemberFollow();
        $followModel->setModelCacheKey('follow',json_encode($postData));

        $data = $followModel->getCache();
        $where_sign = false;
        if(empty($data))
        {
            $product_in = [];
            $followList = $followModel;
            if(array_key_exists('product_name',$postData) && $postData['product_name'] != '')
            {
                $product_in[] = Product::query()->where('name','like',"%{$postData['product_name']}%")->pluck('id')->toArray();
                
                $where_sign = true;
            }
            
            if(array_key_exists('collection_name',$postData) && $postData['collection_name'] != '')
            {
                $where_cid = Collection::query()->where('name','like',"%{$postData['collection_name']}%")->pluck('id')->toArray();
                if(!empty($where_cid))    
                {
                    $product_in[] = Product::query()->whereIn('collection_id',$where_cid)->pluck('id')->toArray();
                }
                else
                {
                    $product_in[] = [];
                }
                
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
                $data = $followList->where('id',0)->orderBy('id','desc')
                    ->paginate(15);
            }
            else
            {
                if(!empty($where_arr))    $followList = $followList->whereIn('product_id',$where_arr);

                $data = $followList->where('status',1)->orderBy('id','desc')
                    ->paginate(15);
            }
            
            foreach($data as $k=>&$v)
            {
                $member = Member::query()->where('id',$v->member_id)->value('username');
                $v->member_name  = $member ?? '';
                $product =  Product::query()->find($v->product_id);
                if(!empty($product))
                {
                    $v->owner           = $product['owner'] ?? '';
                    $v->coin_token      = $product['coin_token'] ?? '';
                    $v->product_name    = $product['name'] ?? '';
                    $v->collection_name = $collecList[$product['collection_id']] ?? '';
                    $v->chain_name      = $chainList[$product['chain_id']] ?? '';
                }
                else
                {
                    $v->owner           = '';
                    $v->coin_token      = '';
                    $v->product_name    = '';
                    $v->collection_name = '';
                    $v->chain_name      = '';
                }
            }

            $followModel->synCache($data,1800);
        }

        return $this->success([
            'list' => $data
        ]);
    }







}