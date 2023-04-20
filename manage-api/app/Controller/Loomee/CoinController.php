<?php

declare(strict_types=1);

namespace App\Controller\Loomee;

use App\Controller\AbstractController;
use App\Model\Loomee\CollectionCategory;
use App\Model\Loomee\Chain;
use App\Model\Loomee\Coin;
use App\Constants\StatusCode;
use Exception;

class CoinController extends AbstractController
{

    /**
     * 列表
     *
     * @return void
     */
    public function index()
    {
        $postData = $this->request->all();
      
        $chainList = (new Chain())->cacheDataList(['name','id']);

        $coinModel = new Coin();
        $coinModel->setModelCacheKey('index',json_encode($postData));

        $data = $coinModel->getCache();
        if(empty($data))
        {
            $coinList = $coinModel;
            if (array_key_exists('chain_id', $postData) && (int)$postData['chain_id'] != 0)
                $coinList = $coinList->where('chain_id', (int)$postData['chain_id']);

            $data = $coinList->orderBy('id', 'desc')
                ->paginate(15);

            foreach ($data as $k => &$v) {
                $v->chain_name = $chainList[$v->chain_id] ?? '';
                $v->application_ids = explode(',',$v->application_ids);
                $v->trade_fee = $v->trade_fee * 100;
            }

            $coinModel->synCache($data);
        }

        return $this->success([
            'list' => $data
        ]);
    }


    /**
     * 更新
     *
     * @param integer $id
     * @return void
     */
    public function update(int $id)
    {
        $coin = Coin::query()->find($id);
        if (empty($coin))   $this->throwExp(StatusCode::USER_NOTEXISTS, '记录不存在');

        $postData = $this->request->all();
        $params = [
            'chain_id'          => $postData['chain_id'] ?? '',
            'name'              => $postData['name'] ?? '',
            'image'             => $postData['image'] ?? '',
            'contract'          => $postData['contract'] ?? '',
            'trade_fee'         => $postData['trade_fee'],
            'application_ids'   => implode(',',$postData['application_ids']) ?? '',
            'status'            => (int)$postData['status'],
        ];

        //配置验证
        $rules = [
            'chain_id'          => 'required|int',
            'name'              => 'required',
            'contract'          => 'required',
            'trade_fee'         => 'required',
            'application_ids'   => 'required',
        ];
        $message = [
            'chain_id.required'         => '请选择所属链',
            'chain_id.int'              => '所属链有误',
            'name.required'             => '币种名不能为空',
            'contract.required'         => '合约地址不能为空',
            'trade_fee.required'        => '请设置交易服务费',
            'application_ids.required'  => '请选择用途',
        ];

        $this->verifyParams($params, $rules, $message);

        $trade_fee = $params['trade_fee'];
        while(true)
        {
            $trade_fee = $trade_fee/100;
            if($trade_fee <= 1) break;
        }
        
        $coin->chain_id        = $params['chain_id'];
        $coin->name            = $params['name'];
        $coin->image           = $params['image'];
        $coin->contract        = $params['contract'];
        $coin->trade_fee       = $trade_fee;
        $coin->application_ids = $params['application_ids'];
        $coin->status          = $params['status'];

        try{
            if (!$coin->save()) $this->throwExp(StatusCode::ERR_VALIDATION, '修改失败');
        }
        catch(Exception $e){
            $this->throwExp(StatusCode::ERR_VALIDATION, '修改失败');
        }
        return $this->successByMessage('修改成功');
    }

    /**
     * 新增
     *
     * @return void
     */
    public function add()
    {
        $postData = $this->request->all();
        $params = [
            'chain_id'          => $postData['chain_id'],
            'name'              => $postData['name'] ?? '',
            'image'             => $postData['image'] ?? '',
            'contract'          => $postData['contract'] ?? '',
            'trade_fee'         => $postData['trade_fee'],
            'application_ids'   => implode(',',$postData['application_ids']) ?? '',
            'status'            => (int)$postData['status'],
        ];

        //配置验证
        $rules = [
            'chain_id'          => 'required|int',
            'name'              => 'required',
            'contract'          => 'required',
            'trade_fee'         => 'required',
            'application_ids'   => 'required',
        ];
        $message = [
            'chain_id.required'         => '请选择所属链',
            'chain_id.int'              => '所属链有误',
            'name.required'             => '币种名不能为空',
            'contract.required'         => '合约地址不能为空',
            'trade_fee.required'        => '请设置交易服务费',
            'application_ids.required'  => '请选择用途',
        ];

        $this->verifyParams($params, $rules, $message);
        $coin = new Coin();

        $trade_fee = $params['trade_fee'];
        while(true)
        {
            $trade_fee = $trade_fee/100;
            if($trade_fee <= 1) break;
        }
        
        $coin->chain_id        = $params['chain_id'];
        $coin->name            = $params['name'];
        $coin->image           = $params['image'];
        $coin->contract        = $params['contract'];
        $coin->trade_fee       = $trade_fee;
        $coin->application_ids = $params['application_ids'];
        $coin->status          = $params['status'];

        try{
            if (!$coin->save()) $this->throwExp(StatusCode::ERR_VALIDATION, '添加失败');
        }
        catch(Exception $e){
            $this->throwExp(StatusCode::ERR_VALIDATION, '添加失败');
        }

        return $this->successByMessage('添加成功');
    }
}
