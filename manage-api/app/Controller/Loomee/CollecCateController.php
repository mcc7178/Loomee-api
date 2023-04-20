<?php

declare(strict_types=1);

namespace App\Controller\Loomee;

use App\Controller\AbstractController;
use App\Model\Loomee\CollectionCategory;
use App\Constants\StatusCode;
use App\Model\Loomee\Collection;
use Exception;

class CollecCateController extends AbstractController
{

    /**
     * 合集分类列表
     *
     * @return void
     */
    public function index()
    {
        $postData = $this->request->all();

        $collecList = new CollectionCategory();
        $collecList->setModelCacheKey('index',json_encode($postData));

        $data = $collecList->getCache();
        if(empty($data))
        {
            if (array_key_exists('name', $postData) && $postData['name'] != '') {
                $data = $collecList->where('name', 'like', "%{$postData['name']}%")->orderBy('id', 'desc')->get()->toArray();
            } else {
                $data = $collecList->orderBy('id', 'desc')->get()->toArray();
            }
            
            $collecList->synCache($data);
        }

        return $this->success([
            'list' => $data
        ]);
    }

    /**
     * 合集分类信息
     *
     * @param integer $id
     * @return void
     */
    public function getOne(int $id)
    {
        $cate = CollectionCategory::query()->find($id);

        return $this->success([
            'list' => $cate,
        ]);
    }


    /**
     * 更新合集分类信息
     *
     * @param integer $id
     * @return void
     */
    public function updateData(int $id)
    {
        $colle = CollectionCategory::query()->find($id);
        if (empty($colle))   $this->throwExp(StatusCode::USER_NOTEXISTS, '合集分类不存在');

        $postData = $this->request->all();
        $params = [
            'name'          => $postData['name'] ?? '',
            // 'status'        => $postData['status'],
            'desctiption'   => $postData['desctiption'] ?? '',
            'icon'          => $postData['icon'] ?? '',
        ];

        //配置验证
        $rules = [
            'name'       => 'required',
            // 'status'        => 'required|int',
        ];
        $message = [
            'name.required'  => '分类名不能为空',
            // 'status.required'   => '请选择合集分类状态',
            // 'status.int'        => '合集分类状态有误',
        ];

        $this->verifyParams($params, $rules, $message);

        $colle->name        = $params['name'];
        $colle->desctiption = $params['desctiption'];
        $colle->icon        = $params['icon'];
        $colle->status      = 1;
        $colle->updated_at  = date('Y-m-d H:i:s');

        try{
            $colle->save();
        }
        catch(Exception $e){
            $this->throwExp(StatusCode::ERR_VALIDATION, '修改失败');
        }

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
            'name'          => $postData['name'] ?? '',
            // 'status'        => $postData['status'],
            'desctiption'   => $postData['desctiption'] ?? '',
            'icon'          => $postData['icon'] ?? '',
        ];

        //配置验证
        $rules = [
            'name'       => 'required',
            // 'status'        => 'required|int',
        ];
        $message = [
            'name.required'  => '分类名不能为空',
            // 'status.required'   => '请选择合集分类状态',
            // 'status.int'        => '合集分类状态有误',
        ];

        $this->verifyParams($params, $rules, $message);
        $colle = new CollectionCategory();

        $colle->name        = $params['name'];
        $colle->desctiption = $params['desctiption'];
        $colle->icon        = $params['icon'];
        $colle->status      = 1;
        $colle->created_at  = date('Y-m-d H:i:s');
        $colle->updated_at  = date('Y-m-d H:i:s');
        try{
            $colle->save();
        }
        catch(Exception $e){
            $this->throwExp(StatusCode::ERR_VALIDATION, '添加失败');
        }

        return $this->successByMessage('添加成功');
    }

    /**
     * @param int $id
     */
    public function destroy(int $id)
    {
        $params = [
            'id' => $id,
        ];
        //配置验证
        $rules = [
            'id' => 'required|int',
        ];
        $message = [
            'id.required' => '非法参数',
            'id.int' => '非法参数',
        ];

        $this->verifyParams($params, $rules, $message);

        $colle = CollectionCategory::query()->find($id);
        if (empty($colle))   $this->throwExp(StatusCode::USER_NOTEXISTS, '合集分类不存在');

        if (!Collection::query()->where('cate_id', $id)->get()->isEmpty()) $this->throwExp(StatusCode::ERR_VALIDATION, '该分类下还有合集，删除失败');

        if (!$colle->delete()) $this->throwExp(StatusCode::ERR_VALIDATION, '删除权限信息失败');
        return $this->successByMessage('删除成功');
    }
}
