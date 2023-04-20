<?php

namespace App\Services;

use App\Services\BaseService;
use App\Model\User;
use App\Model\UserLoginLog;
use Illuminate\Support\Facades\Redis;
use Jenssegers\Agent\Facades\Agent;
use Validator;

class LoginService extends BaseService{


    public static function createVarCode(){
        $img = imagecreatetruecolor(60, 30);
        $black = imagecolorallocate($img, 0x00, 0x00, 0x00);
        $green = imagecolorallocate($img, 0x00, 0xFF, 0x00);
        $white = imagecolorallocate($img, 0xFF, 0xFF, 0xFF);
        imagefill($img, 0, 0, $white);
        //生成随机的验证码
        $code = '';
        for ($i = 0; $i < 4; $i++) {  //4位数的验证码
            $code .= rand(0, 9);
        }

        imagestring($img, 10, 10, 10, $code, $black);
        for ($i = 0; $i < 50; $i++) {
            imagesetpixel($img, rand(0, 100), rand(0, 100), $black);  //imagesetpixel — 画一个单一像素，语法: bool imagesetpixel ( resource $image , int $x , int $y , int $color )
            imagesetpixel($img, rand(0, 100), rand(0, 100), $green);
        }
        ob_start();
        imagejpeg($img);
        $image_data = ob_get_contents();
        ob_end_clean();
        $image_data_base64 = base64_encode($image_data);
        $token = static::random_string();
        //(new CaptchaRedis())->set_captcha_token($code, $token);
        return [
            'image' => $image_data_base64,
            'image_token' => $token,
            'code' => $code
        ];
    }


    public static function checkVarCode($image_token,$code){

    }

    public static function getRequestSource(){
        if(Agent::isMobile()){
            if(Agent::isAndroidOS()){
                return 'android';
            }else{
                return 'ios';
            }
        }else{
            return 'pc';
        }
    }


    public static function addLoginCache($user_id){
        $key = 'forbid_login_'.$user_id;
        Redis::incr($key);
        Redis::expire($key,60*5);
        return true;
    }

    public static function checkLoginCache($user_id){
        $val = Redis::get('forbid_login_'.$user_id);
        if($val && $val >= 5){
            return false;
        }
        return true;
    }


    public static function setVerityToken($token){
        if(!$token){
            static::addError('参数不完整',400);
            return false;
        }

        Redis::setex('verity_code_token:'.$token,15*60,1);
        return true;
    }

    public static function getVerityToken($token){
        if(!$token){
            static::addError('参数不完整',400);
            return false;
        }


        if(!Redis::get('verity_code_token:'.$token)){
            static::addError('验证过期',400);
            return false;
        }
        return true;
    }

    public static function createInviteCode($user_id){
        $code = "ABCDEFGHIGKLMNOPQRSTUVWXYZ";


        $rand = $code[rand(0, 25)] . strtoupper(dechex(date('m')))
            . date('d') . substr(time(), -5)
            . substr(microtime(), 2, 5) . sprintf('%02d', rand(0, 99));
        for (
            $a = md5($rand.$user_id, true),
            $s = '0123456789ABCDEFGHIJKLMNOPQRSTUV',
            $d = '',
            $f = 0;
            $f < 8;
            $g = ord($a[$f]), // ord（）函数获取首字母的 的 ASCII值
            $d .= $s[($g ^ ord($a[$f + 8])) - $g & 0x1F],
            $f++
        ) ;
        if(User::where('invite_code',$d)->first()){
            self::createInviteCode($user_id);
        }
        return $d;
    }

    public static function createActivationCode($user_id){
        $code = "ABCDEFGHIGKLMNOPQRSTUVWXYZ";

        $rand = $code[rand(0, 25)] . strtoupper(dechex(date('m')))
            . date('d') . substr(time(), -5)
            . substr(microtime(), 2, 8) . sprintf('%02d', rand(0, 99));
        for (
            $a = md5($rand.$user_id, true),
            $s = '0123456789ABCDEFGHIJKLMNOPQRSTUV',
            $d = '',
            $f = 0;
            $f < 11;
            $g = ord($a[$f]), // ord（）函数获取首字母的 的 ASCII值
            $d .= $s[($g ^ ord($a[$f + 1])) - $g & 0x1F],
            $f++
        ) ;

        if(User::where('invite_code',$d)->first()){
            self::createInviteCode($user_id);
        }
        return 'LIB'.$d;
    }

    public static function checkAccountType($account){

        $validate = Validator::make(['account' => $account], ['account'=> 'E-Mail']);
        if(!$validate->fails()){
            return User::LOGIN_TYPE_EMAIL;
        }

        $china_validate = Validator::make(['account' => $account], ['account'=> 'regex:/^1[345789]\d{9}$/']);
        $other_validate = Validator::make(['account' => $account], ['account'=> 'min:6']);
        if(!$china_validate->fails() || !$other_validate->fails()){
            return User::LOGIN_TYPE_PHONE;
        }
        static::addError('账号类型错误',400);
        return false;

    }
}
