<?php

declare(strict_types=1);

namespace App\Controller;
use App\Controller\AbstractController;
use App\Service\Auth\UserService;
use App\Model\Auth\Member;
use App\Model\System\WebHomePlaform;
use App\Model\Collection\Collection;
use App\Model\System\WebContentConfig;
use Psr\Http\Message\ServerRequestInterface;
use App\Utils\Cache;
use Hyperf\Utils\ApplicationContext;
class HomeController extends AbstractController
{

    public function index(ServerRequestInterface $request)
    {
        $member=[];
        $token = $request->getHeaderLine('Authorization');
        if($token){
            $userInfo = UserService::getInstance()->getUserInfoByToken();
            if(isset($userInfo->id)&&$userInfo->id){
                $member=Member::query()->select('id','username','avatar')->where('id',$userInfo->id)->first();
            }
        }
//        $container = ApplicationContext::getContainer();
//        $redis = $container->get(\Hyperf\Redis\Redis::class);
//
//
//        $webHomePlaform=$redis->hGetAll('webHomePlaform');
//        if(empty($webHomePlaform)){
//            $webHomePlaform=WebHomePlaform::query()->first();
//            $redis->hMset('webHomePlaform',$webHomePlaform->toArray());
//            $redis->expire('webHomePlaform',600);
//        }
//
//
//        $ad_space=$redis->get('ad_space');
//        if(empty($ad_space)){
//            $ad_space=Collection::query()->where('ad_space',1)->where('status',1)->where('deleted_at',null)->select('id','picture','name','logo','desctiption')->limit(5)->get();
//            $redis->set('ad_space',json_encode($ad_space),600);
//        }else{
//            $ad_space=json_decode($ad_space);
//        }
//
//
//
//        $secondConfig=$redis->get('secondConfig');
//        if(empty($secondConfig)){
//            $secondConfig=WebContentConfig::query()->where('belong_page',2)->where('show',1)->limit(4)->get();
//            $redis->set('secondConfig',json_encode($secondConfig),600);
//        }else{
//            $secondConfig=json_decode($secondConfig);
//        }
//
//
//        $recommend_space=$redis->get('recommend_space');
//        if(empty($recommend_space)){
//            $recommend_space=Collection::query()->where('recommend_space',1)->where('status',1)->where('deleted_at',null)->select('id','picture','name','logo','desctiption')->limit(8)->get();
//            $redis->set('recommend_space',json_encode($recommend_space),600);
//        }else{
//            $recommend_space=json_decode($recommend_space);
//        }
//
//
//        $fourConfig=$redis->get('fourConfig');
//        if(empty($fourConfig)){
//            $fourConfig=WebContentConfig::query()->where('belong_page',4)->where('show',1)->limit(3)->get();
//            $redis->set('fourConfig',json_encode($fourConfig),600);
//        }else{
//            $fourConfig=json_decode($fourConfig);
//        }
        

         $webHomePlaform=WebHomePlaform::query()->first();
         $ad_space=Collection::query()->where('ad_space',1)->where('status',1)->where('deleted_at',null)->select('id','picture','name','logo','desctiption')->limit(5)->get();
         $secondConfig=WebContentConfig::query()->where('belong_page',2)->where('show',1)->orderBy('sort','asc')->limit(4)->get();
         $recommend_space=Collection::query()->where('recommend_space',1)->where('status',1)->where('deleted_at',null)->select('id','picture','name','logo','desctiption')->limit(8)->get();
         $fourConfig=WebContentConfig::query()->where('belong_page',4)->where('show',1)->limit(3)->get();
        return $this->success([
            'member'=>$member,
            'webHomePlaform'=>$webHomePlaform,
            'ad_space'=>$ad_space,
            'secondConfig'=>$secondConfig,
            'recommend_space'=>$recommend_space,
            'fourConfig'=>$fourConfig
        ]);
    }
}