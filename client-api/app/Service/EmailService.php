<?php

namespace App\Services;

use App\Services\BaseService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;

class EmailService extends BaseService{

    public static $content;
    public static $verify_code;
    public static $address;
    public static $verity_token;

    //发送邮件
	public function send(string $address, $title,$content = null)
	{
        if((!self::$content && !$content) || !$address){
            static::addError('参数不完整',400);
            return false;
        }
        
        if(self::getLimit($address)){
            static::addError('操作过于频繁，请稍后重试',400);
            return false;
        }

        self::$address = $address;
        $content = $content ?: self::$content;
		$result = Mail::raw($content, function($message)use($address,$title){
            return $message->to($address)->subject($title);
        });
        if($result){
            static::addError($result,400);
            return false;
        }
        self::setLimit($address);
        return $this;
    }

    //设置时间限制
    public static function setLimit($email){
        Redis::setex('email:'.$email,60,1);
        return true;
    }

    //检查时间限制
    public static function getLimit($email){
        return Redis::get('email:'.$email);
    }
    
    //生成邮件内容
    public static function createContent(){
        self::$verify_code = rand(111111, 999999);
        self::$content = '【GCoin】您的验证码是'.self::$verify_code.'请在5分钟内验证，切勿泄露给他人。如非本人操作，请忽略。';
        return new self;
    }

    //存储
    public function storageCode($user_id = 0){

        if(!self::$content || !self::$verify_code || !self::$address){
            static::addError('参数不完整',400);
            return false;
        }

        self::$verity_token = str_random(60);
        $key = 'email:'.self::$verity_token.self::$address;
        $key = $user_id ? $key.$user_id : $key;

        Redis::setex($key,5*60,self::$verify_code);
        return $this;
    }

    public function getCode(){
        return self::$verify_code;
    }

    public function gettoken(){
        return self::$verity_token;
    }

    //获取
    public static function getCodeByCache($address,$verity_token,$user_id = 0){
        if(!$address){
            static::addError('参数不完整',400);
            return false;
        }

        $key = 'email:'.$verity_token.$address;
        $key = $user_id ? $key.$user_id : $key;

        $var_code = Redis::get($key);
        if(!$var_code){
            static::addError('验证码不存在或已失效',400);
            return false;
        }
        
        return $var_code;
    }

    //删除
    public static function delCode($address,$verity_token,$user_id = 0){
        $key = 'email:'.$verity_token.$address;
        $key = $user_id ? $key.$user_id : $key;

        Redis::del($key);
        return true;
    }

    //检查
    public static function checkCode($address,$input_code,$verity_token,$user_id = 0){
        if (env('APP_DEBUG')){
            return true;
        }
        if(!$address || !$input_code){
            static::addError('邮箱或验证码不存在',400);
            return false;
        }
        $var_code = self::getCodeByCache($address,$verity_token,$user_id);
        if(!$var_code){
            return false;
        }

        if((int)$var_code != (int)$input_code){
            static::addError('验证码错误',400);
            return false;
        }
        //self::delCode($address,$verity_token,$user_id);
        return true;
    }
}