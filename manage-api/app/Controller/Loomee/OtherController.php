<?php

declare(strict_types=1);

namespace App\Controller\Loomee;

use App\Controller\AbstractController;
use App\Model\Loomee\Chain;
use App\Constants\StatusCode;

class OtherController extends AbstractController
{
    
    /**
     * 链列表
     *
     * @return void
     */
    public function index()
    {
        $chainModel = new Chain();
        $chainModel->setModelCacheKey('index','index');

        $data = $chainModel->getCache();
        if(empty($data))
        {
            $data = $chainModel->orderBy('id','asc')->get()->toArray(); 
            $chainModel->synCache($data);
        }

        return $this->success([
            'list' => $data,
        ]);
    }

    
    /**
     * 单个链表信息
     *
     * @param integer $id
     * @return void
     */
    public function chainGetOne(int $id)
    {
        $ch = Chain::query()->find($id);
    
        if(empty($ch))        $this->throwExp(StatusCode::USER_NOTEXISTS, '合集不存在');

        return $this->success([
            'list' => $ch,
        ]);
    }
    
    
    /**
     * 更新
     *
     * @param integer $id
     * @return void
     */
    public function chainUpdate(int $id)
    {
        $colle = Chain::query()->find($id);
        if(empty($colle))        $this->throwExp(StatusCode::USER_NOTEXISTS, '链不存在');

        $postData = $this->request->all();
        $params = [
            'name'      => $postData['name'] ?? '',
            'picture'   => $postData['picture'] ?? '',
            'remark'    => $postData['remark'] ?? '',
            'status'    => $postData['status'] ?? '',
        ];

        $rules = [
            'name' => 'required',
            'status' => 'required|int',
        ];
        $message = [
            'name.required' => ' 链名不能为空 ',
            'status.required' => ' 请选择状态 ',
            'status.int' => ' 状态有误 ',
        ];
        $this->verifyParams($params, $rules, $message);

        $colle->name    = $params['name'];
        $colle->picture = $params['picture'];
        $colle->remark  = $params['remark'];
        $colle->status  = $params['status'];
        $colle->save();

        return $this->successByMessage('修改成功');
    }
    

    /**
     * 新增
     *
     * @return void
     */
    public function chainAdd()
    {
        $postData = $this->request->all();
        $params = [
            'name' => $postData['name'] ?? '',
            'picture' => $postData['picture'] ?? '',
            'remark' => $postData['remark'] ?? '',
            'status' => $postData['status'] ?? 0,
        ];

        $rules = [
            'name' => 'required',
            'status' => 'required|int',
        ];
        $message = [
            'name.required' => ' 链名不能为空 ',
            'status.required' => ' 请选择状态 ',
            'status.int' => ' 状态有误 ',
        ];
        $this->verifyParams($params, $rules, $message);

        $chain = new Chain();
        $chain->name = $params['name'];
        $chain->picture = $params['picture'];
        $chain->remark = $params['remark'];
        $chain->status = $params['status'];

        $chain->save();

        return $this->successByMessage('添加成功');
    }
    


























}