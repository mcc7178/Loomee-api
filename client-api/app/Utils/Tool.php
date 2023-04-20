<?php

namespace App\Utils;

use App\Model\User;

class Tool{

    /*
     * 生成用户邀请码
     */
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
        if(User::query()->where('invite_code',$d)->first()){
            self::createInviteCode($user_id);
        }
        return $d;
    }

    /*
     * 生成数字订单号
     */
    public static function createOrderNo(){
        //20210824000000
        return date('mdHis') . str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
    }

    /* 算取时间戳的整分 */
    public static function getNowTime($from, $step)
    {
        $from = strtotime(date("Y-m-d H:i", $from));
        if ($step == 1){

        }elseif ($step == 1440){
            $from = strtotime(date("Y-m-d", $from)) - 86400;
        }else{
            $minute = date("i", $from);
            $remainder = $minute % $step;
            if ($remainder != 0)
                $from = $from - $remainder * 60;
        }
        return $from;
    }
}