<?php

namespace App\Services;

use App\Exceptions\BaseException;
use App\Services\BaseService;
use App\Model\User;
use App\Model\UserLoginLog;
use Illuminate\Support\Facades\Redis;
use App\Services\LoginService;
use App\Model\UserImpLog;


class UserService extends BaseService{

    public static function getUserById($user_id){
        $user = User::find($user_id);
        if(!$user_id){
            static::addError('用户不存在',404);
            return false;
        }
        return $user->toArray();
    }


    public static function getUserByPhone($phone){
        if(!$phone){
            static::addError('请传入手机号',400);
            return false;
        }

        $user = User::where('phone',$phone)->first();
        if(!$user){
            static::addError('用户不存在',400);
            return false;
        }

        return $user->toArray();
    }

    public static function getUserByEmail($email){
        if(!$email){
            static::addError('请传入email',400);
            return false;
        }

        $user = User::where('email',$email)->first();
        if(!$user){
            static::addError('用户不存在',400);
            return false;
        }

        return $user->toArray();
    }


    public static function getUserByPhoneOrEmail($user_name){
        if(!$user_name){
            static::addError('请输入用户账号',400);
            return false;
        }

        $user = User::where('phone',$user_name)->orWhere('email',$user_name)->first();
        if(!$user){
            static::addError('用户不存在',400);
            return false;
        }

        return $user->toArray();
    }

    public static function addUserLoginLog(array $user,$ip,$source,$type){
        if(!$user || !$ip){
            static::addError('参数不完整',400);
            return false;
        }

        $insert_data = [
            'userid' => $user['id'],
            'ip' => \DB::raw("INET_ATON('$ip')"),
            'created_time' => time(),
            'source' => $source,
            'import_type' => $type
        ];
        UserLoginLog::create($insert_data);
        return true;
    }

    public static function getUserByInviteCode($invite_code){
        if(!$invite_code){
            static::addError('请输入用户账号',400);
            return false;
        }

        $user = User::where('invite_code',$invite_code)->first();
        if(!$user){
            static::addError('用户不存在',400);
            return false;
        }

        return $user->toArray();
    }


    public static function registerUser(array $data){
        if(!$data){
            static::addError('参数不完整',400);
            return false;
        }
        $data['password'] = password_hash($data['password'],PASSWORD_BCRYPT);
        if(isset($data['paypassword']) && $data['paypassword']){
            $data['paypassword'] = password_hash($data['paypassword'],PASSWORD_BCRYPT);
        }
        return User::create($data);
    }

    public static function updateUser($user_id,array $data){
        if(!$data || !$user_id){
            static::addError('参数不完整',400);
            return false;
        }

        if(isset($data['password']) && $data['password']){
            $data['password'] = password_hash($data['password'],PASSWORD_BCRYPT);
        }
        if(isset($data['paypassword']) && $data['paypassword']){
            $data['paypassword'] = password_hash($data['paypassword'],PASSWORD_BCRYPT);
        }

        return  User::where('id',$user_id)->update($data);

    }

    public static function getUserByCache($token){
        $user = Redis::get('user_login:'.$token);
        if(!$user){
            static::addError('登陆已过期',401);
            return false;
        }
        return json_decode($user,true);
    }

    public static function addUserCache($client,$token,$user_id,array $data){
        if (!$client || !$token || !$data) {
            static::addError('参数不完整', 401);
            return false;
        }

//        Redis::setex('user_login:'.$token,7*24*60*60,json_encode($data));
        Redis::set('user_login:' . $token, json_encode($data));
        return true;
    }

    public static function setLoginClientCache($client,$user_id,$token){
        $client = $client == 'pc' ? 'pc' : 'mobile';
        Redis::hset('user_login_hash:'.$client,$user_id,$token);
        return true;
    }

    public static function getLoginClientCache($client,$user_id){
        // $client = $client == 'pc' ? 'pc' : 'mobile';
        $client = 'mobile';
        $token = Redis::hget('user_login_hash:'.$client,$user_id);
        return $token;
    }

    public static function updateUserCache($token,array $data){
        $user = Redis::get('user_login:'.$token);
        if(!$user){
            static::addError('登陆已过期',401);
            return false;
        }
        $user = json_decode($user,true);

        foreach($data as $key => $val){
            if(isset($user[$key])){
                $user[$key] = $val;
            }
        }

        Redis::setex('user_login:'.$token,7*60*60,json_encode($user));
        return true;

    }

    //验证密码
    public static function checkPwd($input_pwd,$user_pay_pwd){
        if(!$input_pwd || !$user_pay_pwd){
            static::addError('参数不完整',400);
            return false;
        }

        if(!password_verify($input_pwd,$user_pay_pwd)){
            static::addError('密码错误',400);
            return false;
        }

        return true;
    }

    public static function clearUserCache($token){
        Redis::del('user_login:'.$token);
        return true;
    }

    public static function clearUserLoginHash($user_id,$client = ''){
        if($client){
            Redis::hdel('user_login_hash:'.$client,[$user_id]);
        }else{
            Redis::hdel('user_login_hash:pc',[$user_id]);
            Redis::hdel('user_login_hash:mobile',[$user_id]);
        }
        return true;
    }

    //设置提币操作输入资金密码操作的返回token
    public static function setWithdrawPaypwdCache($user_id,$token){
        Redis::setex('withdraw_paypwd_token:'.$token.$user_id,24*60*60,1);
    }

    //验证提币操作输入资金密码操作的返回token
    public static function checkWithdrawPaypwdToken($user_id,$token){
        $result = Redis::get('withdraw_paypwd_token:'.$token.$user_id);
        if(!$result){
            static::addError('密码验证校验失败',400);
            return false;
        }
        Redis::del('withdraw_paypwd_token:'.$token.$user_id);
        return true;
    }

    //设置提币安全验证缓存
    public static function setWithdrawAuthCache($user_id,$token){
        Redis::setex('withdraw_auth_token:'.$token.$user_id,24*60*60,1);
    }

    //获取提币安全验证缓存
    public static function checkWithdrawAuthToekn($user_id,$token){
        $result = Redis::get('withdraw_auth_token:'.$token.$user_id);
        if(!$result){
            static::addError('安全验证校验失败',400);
            return false;
        }
        Redis::del('withdraw_auth_token:'.$token.$user_id);
        return true;
    }


    //修改密码日志
    public static function updatePwdLog($user_id,$type,$ip,array $params){
        $data['agent'] = $params['source'];
        $data['params'] = json_encode($params);
        $data['type'] = $type;
        $data['userid'] = $user_id;
        $data['ip'] = \DB::raw("INET_ATON('$ip')");
        $data['created_time'] = time();

        UserImpLog::create($data);
        return true;
    }

    public static function checkUserData($userid, $type)
    {
        if (!is_numeric($userid))
        {
            return false;
        }
        $user = User::query()->find($userid);
        if (!$user)
        {
            return false;
        }
        switch ($type)
        {
            case 'is_realname':
                $res = $user->is_realname;
                break;
            case 'paypassword':
                $res = $user->paypassword;
                break;
            default:
                $res = false;
        }
        return $res;
    }

    public static function checkActivationStatus($userid)
    {
        if (!User::query()->where('id', $userid)->value('activation_status'))
            throw new BaseException(trans('msg.Please activate first'));
    }




}
