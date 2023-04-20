<?php

namespace App\Services;

use App\Model\UserRealAuth;
use Illuminate\Http\Request;

class UserAuthService extends BaseService{


    public static function authSubmit(Request $request){
        $insert_data = [
            'state_id' => $request->input('state_id') ?: 1,
            'userid' => $request->user['user_id'],
            'type' => $request->input('type'),
            'name' => $request->input('name'),
            'number' => $request->input('number'),
            'status' => 0,
            'created_time' => time(),
            'updated_time' => time()
        ];
        $images['img_front'] = $request->file('img_front');
        $images['img_back'] = $request->file('img_back');
//        $images['img_hold'] = $request->file('img_hold');
        //$scheme = empty($_SERVER['HTTPS']) ? 'http://' : 'https://';
        //$host_name = $scheme.$_SERVER['HTTP_HOST'].'/';
        
        foreach($images as $key => $image){
            $insert_data[$key] = self::preserveImg($image);
            if(!$insert_data[$key]){
                return false;
            }
        }
        UserRealAuth::updateOrCreate(['userid' => $request->user['user_id']],$insert_data);
        return true;

    }

    public static function preserveImg($image){
        if(!$image->isValid()){
            static::addError('无效的图片',400);
            return false;
        }
        
        // 文件扩展名
        $extension = $image->getClientOriginalExtension();
        // 文件名
        $fileName = $image->getClientOriginalName();
        // 生成新的统一格式的文件名
        $newFileName = md5($fileName . time() . mt_rand(1, 10000)) . '.' . $extension;
        // 图片保存路径
        $webPath = 'image/' . $newFileName;

        // 执行保存操作，保存成功将访问路径返回给调用方
        if ($image->move('image',$newFileName)) {
            return $webPath;
        }
        return false;
        
    }

    public static function getAuthStatus($user_id){
        $result = UserRealAuth::where('userid',$user_id)->select('status','note','check_time')->first();
        if(!$result){
            // static::addError('没有审核记录',400);
            // return false;
            return [];
        }

        return $result->toArray();
    }

    
}