<?php

declare(strict_types=1);

namespace App\Controller\Setting;

use App\Controller\AbstractController;
use App\Service\Setting\ServeMonitorService;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use Hyperf\HttpServer\Annotation\RequestMapping;
use App\Middleware\RequestMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Model\Setting\WebHomePlaform;
use App\Model\Setting\WebContentConfig;
use App\Constants\StatusCode;
use Hyperf\Paginator\Paginator;
use Hyperf\HttpServer\Contract\RequestInterface;

/**
 * 首页设置控制器
 */
class HomeController extends AbstractController
{
    /**
     * 基础设置列表
     */
    public function basic()
    {
        $list=WebHomePlaform::query()->orderBy('id', 'desc')->get()->toArray();
        return $this->success($list);
    }
    /**
     * 基础设置编辑
     */
    public function basicEdit(int $id)
    {
        $result=WebHomePlaform::query()->find($id);
        if (empty($result))   $this->throwExp(StatusCode::USER_NOTEXISTS, '记录不存在');
        $postData = $this->request->all();
        $params = [
            'first_slogan'     => $postData['first_slogan'] ?? '',
            'roll_msg'      => $postData['roll_msg'] ?? '',
            'second_title'    => $postData['second_title'] ?? '',
            'second_msg'       => $postData['second_msg'] ?? '',
            'fourth_title'       => $postData['fourth_title'] ?? '',
        ];
        $result->first_slogan    = $params['first_slogan'];
        $result->roll_msg     = $params['roll_msg'];
        $result->second_title   = $params['second_title'];
        $result->second_msg      = $params['second_msg'];
        $result->fourth_title      = $params['fourth_title'];

        if (!$result->save()) $this->throwExp(StatusCode::ERR_VALIDATION, '修改失败');

        return $this->successByMessage('修改成功');
    }



    /**
     * 第二屏和第四屏设置列表
     */
    public function index(int $belong_page)
    {   
        $list=WebContentConfig::query()->where('belong_page',$belong_page)->orderBy('id', 'desc')->paginate(15);
        return $this->success(['list'=>$list]);
    }
    /**
     * 第二屏和第四屏设置添加
     */
    public function add(int $belong_page)
    {
        $postData = $this->request->all();
        $params = [
            'cover_picture'     => $postData['cover_picture'] ?? '',
            'icon'      => $postData['icon'] ?? '',
            'title'    => $postData['title'] ?? '',
            'button'       => $postData['button'] ?? '',
            'jump_url'       => $postData['jump_url'] ?? '',
            'sort'       => $postData['sort'] ?? 0,
            'belong_page'       => $belong_page??2,
            'show'       => $postData['show'] ?? 1,
        ];
        //配置验证
        $rules = [
            'jump_url'          => 'url',
            'icon'              => 'url',
            'cover_picture'     => 'url',
            'title'             => 'between:3,30',
            'button'   => 'between:1,15',
        ];
        $message = [
            'jump_url.url'              => '跳转url不正确',
            'icon.url'              => '图标url格式不正确',
            'cover_picture.url'             => '封面url不正确',
            'title.between'         => '标题3-30字符',
            'button.between'        => '按钮文字1-15字符',
        ];
        $this->verifyParams($params, $rules, $message);
        $WebContentConfig = new WebContentConfig();

        $WebContentConfig->cover_picture    = $params['cover_picture'];
        $WebContentConfig->icon     = $params['icon'];
        $WebContentConfig->title   = $params['title'];
        $WebContentConfig->button      = $params['button'];
        $WebContentConfig->jump_url      = $params['jump_url'];
        $WebContentConfig->sort      = $params['sort'];
        $WebContentConfig->belong_page      = $params['belong_page'];
        $WebContentConfig->show      = $params['show'];

        if (!$WebContentConfig->save()) $this->throwExp(StatusCode::ERR_VALIDATION, '添加失败');

        return $this->successByMessage('添加成功');
    }
    /**
     * 第二屏和第四屏设置编辑
     */
    public function edit(int $belong_page,int $id,RequestInterface $request)
    {
        if($request->isMethod('get')){
            $result=WebContentConfig::query()->where('belong_page',$belong_page)->where('id',$id)->first()->toArray();
            if (empty($result))   $this->throwExp(StatusCode::USER_NOTEXISTS, '记录不存在');
            return $this->success($result); 
        }else if($request->isMethod('post')){
            $result=WebContentConfig::query()->where('belong_page',$belong_page)->where('id',$id)->first();
            if (empty($result))   $this->throwExp(StatusCode::USER_NOTEXISTS, '记录不存在');
            $postData = $this->request->all();
            $params = [
                'cover_picture'     => $postData['cover_picture'] ?? '',
                'icon'      => $postData['icon'] ?? '',
                'title'    => $postData['title'] ?? '',
                'button'       => $postData['button'] ?? '',
                'jump_url'       => $postData['jump_url'] ?? '',
                'sort'       => $postData['sort'] ?? 0,
                'belong_page'       => $belong_page??2,
                'show'       => $postData['show'] ?? 1,
            ];
            //配置验证
            $rules = [
                'jump_url'          => 'url',
                'icon'              => 'url',
                'cover_picture'     => 'url',
                'title'             => 'between:3,30',
                'button'   => 'between:1,15',
            ];
            $message = [
                'jump_url.url'              => '跳转url不正确',
                'icon.url'              => '图标url格式不正确',
                'cover_picture.url'             => '封面url不正确',
                'title.between'         => '标题3-30字符',
                'button.between'        => '按钮文字1-15字符',
            ];
            $this->verifyParams($params, $rules, $message);
            $result->cover_picture    = $params['cover_picture'];
            $result->icon     = $params['icon'];
            $result->title   = $params['title'];
            $result->button      = $params['button'];
            $result->jump_url      = $params['jump_url'];
            $result->sort      = $params['sort'];
            $result->belong_page      = $params['belong_page'];
            $result->show      = $params['show'];

            if (!$result->save()) $this->throwExp(StatusCode::ERR_VALIDATION, '修改失败');
            return $this->successByMessage('修改成功');
        }  
    }
    public function setShow(int $belong_page,int $id){
        $show = $this->request->input('show');
        if((int)$show>0){
            $count=WebContentConfig::query()->where(['belong_page'=>$belong_page,'show'=>$show])->count();
            if($belong_page==2&&$count>=4){
                $this->throwExp(StatusCode::ERR_VALIDATION, '最多显示4个');
            }
            if($belong_page==4&&$count>=3){
                $this->throwExp(StatusCode::ERR_VALIDATION, '最多显示3个');
            }
        }
        $result=WebContentConfig::query()->where(['belong_page'=>$belong_page,'id'=>$id])->update(['show'=>$show]);
        if(!$result)   $this->throwExp(StatusCode::ERR_VALIDATION, '设置失败');
        return $this->successByMessage('设置成功'); 
    }
}
