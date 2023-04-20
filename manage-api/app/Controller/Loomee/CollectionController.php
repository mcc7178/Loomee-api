<?php

declare(strict_types=1);

namespace App\Controller\Loomee;

use App\Controller\AbstractController;
use App\Model\Loomee\Collection;
use App\Constants\StatusCode;

class CollectionController extends AbstractController
{
    
    /**
     * 合集列表
     *
     * @return void
     */
    public function index()
    {
        $postData = $this->request->all();
        $collecModel = new Collection();
        $collecModel->setModelCacheKey('index',json_encode($postData));

        $data = $collecModel->getCache();
        if(empty($data))
        {
            $collecList = $collecModel;
            if(array_key_exists('collection_name',$postData) && $postData['collection_name'] != '')
                $collecList = $collecList->where('name','like',"%{$postData['collection_name']}%");
            
            if(array_key_exists('chain_id',$postData) && (int)$postData['chain_id'] != 0)
                $collecList = $collecList->where('chain_id',(int)$postData['chain_id']);
            
            if(array_key_exists('cate_id',$postData) && (int)$postData['cate_id'] != 0)
                $collecList = $collecList->where('cate_id',(int)$postData['cate_id']);

            $data = $collecList->orderBy('id','desc')->paginate(15);
            
            foreach($data as $k=>&$v)
            {
                $v->copyright_fee = (string)number_format($v->copyright_fee * 100,2);
            }

            $collecModel->synCache($data);
        }

        return $this->success([
            'list' => $data,
        ]);
    }
    
    /**
     * 合集列表
     *
     * @return void
     */
    public function getList()
    {
        $postData = $this->request->all();
        
        $collecList = new Collection();
        $collecList->setModelCacheKey('getList',json_encode($postData));

        $data = $collecList->getCache();
        if(empty($data))
        {
            $data = $collecList->orderBy('id','desc')->get()->toArray();
            
            $collecList->synCache($data);
        }

        return $this->success([
            'list' => $data,
        ]);
    }

    
    /**
     * 修改合集状态
     *
     * @param integer $id
     * @return void
     */
    public function setStatus(int $id)
    {
        $user = Collection::query()->find($id);
    
        if(empty($user))        $this->throwExp(StatusCode::USER_NOTEXISTS, '合集不存在');

        $status = 0;
        if($user->status == 0)  $status = 1;
        $user->status = $status;
        $user->save();
        
        return $this->successByMessage('修改成功');
    }
    
    
    /**
     * 修改广告设置
     *
     * @param integer $id
     * @return void
     */
    public function setAd(int $id)
    {
        $user = Collection::query()->find($id);
        if(empty($user))        $this->throwExp(StatusCode::USER_NOTEXISTS, '合集不存在');

        $ad_space = 0;
        if($user->ad_space == 0)  $ad_space = 1;
        $user->ad_space = $ad_space;
        $user->save();
        
        return $this->successByMessage('修改成功');
    }
    
    
    /**
     * 修改推荐设置
     *
     * @param integer $id
     * @return void
     */
    public function setRecommend(int $id)
    {
        $user = Collection::query()->find($id);
        if(empty($user))        $this->throwExp(StatusCode::USER_NOTEXISTS, '合集不存在');

        $recommend_space = 0;
        if($user->recommend_space == 0)  $recommend_space = 1;
        $user->recommend_space = $recommend_space;
        $user->save();
        
        return $this->successByMessage('修改成功');
    }
    
    /**
     * 设置版税
     *
     * @param integer $id
     * @return void
     */
    public function setCopyright(int $id)
    {
        $colle = Collection::query()->find($id);
        if(empty($colle))        $this->throwExp(StatusCode::USER_NOTEXISTS, '合集不存在');

        $postData = $this->request->all();
        $params = [
            'copyright_fee' => $postData['copyright_fee'] ?? 0,
            'copyright_url' => $postData['copyright_url'] ?? '',
        ];

        while(true)
        {
            $params['copyright_fee'] = $params['copyright_fee']/100;
            if($params['copyright_fee'] <= 1) break;
        }
        
        $colle->copyright_fee = $params['copyright_fee'];
        $colle->copyright_url = $params['copyright_url'];
        $colle->save();

        return $this->successByMessage('修改成功');
    }
    
    /**
     * 设置属性
     *
     * @param integer $id
     * @return void
     */
    public function setElement(int $id)
    {
        $colle = Collection::query()->find($id);
        if(empty($colle))   $this->throwExp(StatusCode::USER_NOTEXISTS, '合集不存在');;

        $element = $this->request->all()['element'] ?? '';
      
        $colle->element = json_encode($element);
        $colle->save();

        return $this->successByMessage('修改成功');
    }

    /**
     * 更新结集合信息
     *
     * @param integer $id
     * @return void
     */
    public function updateData(int $id)
    {
        $colle = Collection::query()->find($id);
        if(empty($colle))   $this->throwExp(StatusCode::USER_NOTEXISTS, '合集不存在');

        $postData = $this->request->all();
        $params = [
            'desctiption'   => $postData['desctiption']??'',
            'picture'       => $postData['picture']??'',
            'logo'          => $postData['logo']??'',
            'cate_id'       => $postData['cate_id'],
            'status'        => $postData['status'],
            'website'       => $postData['website']??'',
            'twitter'       => $postData['twitter'] ?? '',
            'weibo'         => $postData['weibo'] ?? '',
            'instagram'     => $postData['instagram'] ?? '',
            'discord'       => $postData['discord'] ?? '',
            'telegrm'       => $postData['telegrm'] ?? '',
            'medium'        => $postData['medium'] ?? '',
        ];
  
        //配置验证
        $rules = [
            'cate_id'       => 'required|int',
            'status'        => 'required|int',
        ];
        $message = [
            'cate_id.required'  => '请选择合集分类',
            'cate_id.int'       => '合集分类有误',
            'status.required'   => '请选择合集状态',
            'status.int'        => '合集状态有误',
        ];

        $this->verifyParams($params, $rules, $message);
    
        $colle->desctiption = $params['desctiption'];
        $colle->picture     = $params['picture'];
        $colle->logo        = $params['logo'];
        $colle->cate_id     = $params['cate_id'];
        $colle->status      = $params['status'];
        $colle->website     = $params['website'];
        $colle->twitter     = $postData['twitter'];
        $colle->weibo       = $postData['weibo'];
        $colle->instagram   = $postData['instagram'];
        $colle->discord     = $postData['discord'];
        $colle->telegrm     = $postData['telegrm'];
        $colle->medium      = $postData['medium'];
        $colle->updated_at  = date('Y-m-d H:i:s');
        if($params['status'] == 1)    $colle->shelves_at  = date('Y-m-d H:i:s');
        if (!$colle->save()) $this->throwExp(StatusCode::ERR_VALIDATION, '修改失败');

        return $this->successByMessage('修改成功');
    }

    /**
     * 添加集合
     *
     * @return void
     */
    public function store()
    {
        $postData = $this->request->all();
        $params = [
            'name'          => $postData['name']??'',
            'contract'      => $postData['contract']??'',
            'chain_id'      => $postData['chain_id'],
            'desctiption'   => $postData['desctiption']??'',
            'picture'       => $postData['picture']??'',
            'logo'          => $postData['logo']??'',
            'cate_id'       => $postData['cate_id'],
            'status'        => $postData['status'],
            'website'       => $postData['website']??'',
            'twitter'       => $postData['twitter'] ?? '',
            'weibo'         => $postData['weibo'] ?? '',
            'instagram'     => $postData['instagram'] ?? '',
            'discord'       => $postData['discord'] ?? '',
            'telegrm'       => $postData['telegrm'] ?? '',
            'medium'        => $postData['medium'] ?? '',
        ];
        //配置验证
        $rules = [
            'name'       => 'required',
            'cate_id'       => 'required|int',
            'contract'       => 'required',
            'chain_id'       => 'required|int',
            'status'        => 'required|int',
        ];
        $message = [
            'name.required'     => '合集名称不能为空',
            'cate_id.required'  => '请选择合集分类',
            'cate_id.int'       => '合集分类有误',
            'contract.required' => '合约地址不能为空',
            'chain_id.required' => '请选择合集公链',
            'chain_id.int'      => '合集公链有误',
            'status.required'   => '请选择合集状态',
            'status.int'        => '合集状态有误',
        ];

        $this->verifyParams($params, $rules, $message);

        if (!Collection::query()->where('contract', $params['contract'])->get()->isEmpty()) $this->throwExp(StatusCode::ERR_VALIDATION, '该合约地址已存在，不能重复添加');

        $colle = new Collection();
        $colle->name    = $params['name'];
        $colle->contract    = $params['contract'];
        $colle->desctiption = $params['desctiption'];
        $colle->picture     = $params['picture'];
        $colle->logo        = $params['logo'];
        $colle->cate_id     = $params['cate_id'];
        $colle->chain_id    = $params['chain_id'];
        $colle->status      = $params['status'];
        $colle->website     = $params['website'];
        $colle->twitter     = $postData['twitter'];
        $colle->weibo       = $postData['weibo'];
        $colle->instagram   = $postData['instagram'];
        $colle->discord     = $postData['discord'];
        $colle->telegrm     = $postData['telegrm'];
        $colle->medium      = $postData['medium'];
        $colle->created_at  = date('Y-m-d H:i:s');
        $colle->updated_at  = date('Y-m-d H:i:s');
        if($params['status'] == 1)    $colle->shelves_at  = date('Y-m-d H:i:s');
        $colle->create_user  = conGet('user_info')['id'];
        if (!$colle->save()) $this->throwExp(StatusCode::ERR_VALIDATION, '添加失败');

        return $this->successByMessage('添加成功');
    }










}