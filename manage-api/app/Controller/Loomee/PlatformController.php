<?php

declare(strict_types=1);

namespace App\Controller\Loomee;

use App\Controller\AbstractController;
use App\Model\Loomee\Platform;
use App\Constants\StatusCode;

class PlatformController extends AbstractController
{

    /**
     * 列表
     *
     * @return void
     */
    public function index()
    {  
        $platform = new Platform();
        $platform->setModelCacheKey('index','index');

        $data = $platform->getCache();
        if(empty($data))
        {
            $data = $platform->orderBy('id', 'desc')
                ->get()->toArray();
    
            $platform->synCache($data);
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
        $coin = Platform::query()->find($id);
        if (empty($coin))   $this->throwExp(StatusCode::USER_NOTEXISTS, '记录不存在');

        $postData = $this->request->all();
        $params = [
            'brand_url'     => $postData['brand_url'] ?? '',
            'help_url'      => $postData['help_url'] ?? '',
            'clause_url'    => $postData['clause_url'] ?? '',
            'website'       => $postData['website'] ?? '',
            'twitter'       => $postData['twitter'] ?? '',
            'weibo'         => $postData['weibo'] ?? '',
            'instagram'     => $postData['instagram'] ?? '',
            'discord'       => $postData['discord'] ?? '',
            'telegrm'       => $postData['telegrm'] ?? '',
            'medium'        => $postData['medium'] ?? '',
        ];

        $coin->brand_url    = $params['brand_url'];
        $coin->help_url     = $params['help_url'];
        $coin->clause_url   = $params['clause_url'];
        $coin->website      = $params['website'];
        $coin->twitter      = $params['twitter'];
        $coin->weibo        = $params['weibo'];
        $coin->instagram    = $params['instagram'];
        $coin->discord      = $params['discord'];
        $coin->telegrm      = $params['telegrm'];
        $coin->medium       = $params['medium'];

        if (!$coin->save()) $this->throwExp(StatusCode::ERR_VALIDATION, '修改失败');

        return $this->successByMessage('修改成功');
    }

}
