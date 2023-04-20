<?php


namespace App\Services;



use App\Lib\Redis;

class CaptchaService extends BaseService
{
    public static function getCaptcha()
    {
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
            imagesetpixel($img, rand(0, 100), rand(0, 100), $black);
            imagesetpixel($img, rand(0, 100), rand(0, 100), $green);
        }

        ob_start();

        imagejpeg($img);
        $image_data = ob_get_contents();
        ob_end_clean();
        $image_data_base64 = base64_encode($image_data);
        $token = str_random(60);
        Redis::getInstance(4)->setex('captcha:'.$token, 180, $code);
        return [
            'image' => $image_data_base64,
            'image_token' => $token,
        ];
    }

    /**
     * 验证图片验证码
     * @param $captcha
     * @param $captcha_token
     * @return array
     */
    public static function checkCaptcha($captcha, $captcha_token)
    {
        if (Redis::getInstance(4)->exists('captcha:'.$captcha_token)){
            $cache = Redis::getInstance(4)->get('captcha:'.$captcha_token);
            if ($cache !== $captcha) {
                return ['status' => false, 'message' => '验证码错误'];
            }
        }
        return ['status' => true];
    }

    /**
     * 删除图片验证码cache
     * @param $captcha_token
     */
    public static function delCaptcha($captcha_token)
    {
        Redis::getInstance(4)->del('captcha:'.$captcha_token);
    }


}