<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Controller\AbstractController;
use App\Model\Auth\Member;
use App\Service\Auth\UserService;
use Donjan\Permission\Models\Role;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\RequestMapping;
use League\Flysystem\Filesystem;
use Hyperf\Utils\Context;

class UserController extends AbstractController
{
    /**
     * Notes:用户信息
     * User: Deycecep
     * DateTime: 2022/4/18 11:24
     * @return object
     */
    public function userInfo()
    {
//        $language = $this->request->header('language','en');
//        var_dump($language);
//       $this->translator->setLocale($language);
//        $this->throwExp(400,  __('validation.sell num'));
      //  $userInfo = UserService::getInstance()->getUserInfoByToken();
        $postData = $this->request->all();
        $address = $postData['address'];
        if (!$address)
            $this->throwExp(400,  __('validation.not_empty',['title' => 'address']));
        $userInfo =  UserService::getInstance()->getUserInfoByAddress($address);
        if(!$userInfo){
            return  $this->success([
                'username'=>substr($address,0,6),
                'address' =>$address
                ]
            );
//            $this->throwExp(400, 'address错误');
        }
        if(!$userInfo->username)
            $userInfo->username = strtoupper(substr($userInfo->address,0,6));
        return  $this->success($userInfo);
    }

    /**
     * Notes:修改用户信息
     * User: Deycecep
     * DateTime: 2022/4/18 11:30
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \App\Exception\Handler\BusinessException
     */
    public function userInfoEdit($id)
    {
        if (empty($id)) $this->throwExp(400, __('validation.not_empty',['title' => 'ID']));
        $postData = $this->request->all();

        $rules = [
            'username' => 'alpha_dash|between:4,60',
            'introduction'=>'between:5,400'
        ];
        $message = [
            'username.between' => ' username Length4-60',
            'username.alpha_dash' => ' username Can only contain letters, numbers, underscores, minus signs',
            'introduction.between' => ' Personal bio length 5-400 characters',
        ];
        $this->verifyParams($postData, $rules, $message);

        $userInfo = UserService::getInstance()->getUserInfoByToken();
        if($userInfo->id != $id)
            $this->throwExp(400,  __('validation.not_empty'));
        $user = Member::getOneByUid($id);
        $user->username = $postData['username'] ?? '';
        $user->avatar = $postData['avatar'] ?? '';
        $user->cover = $postData['cover'] ?? '';
        $user->introduction = $postData['introduction'] ?? '';

        if (!$user->save()) $this->throwExp(400,  __('validation.fail_to_edit'));
        return $this->successByMessage(__('validation.success_to_edit'));
    }

}
