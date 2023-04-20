<?php


namespace App\Logic;


use App\Exceptions\BaseException;
use App\Foundation\Common\RedisService;
use App\Http\Repositories\TeamRepository;
use App\Lib\Jwt;
use App\Model\SubscribeOrder;
use App\Utils\Redis;
use App\Model\User;
use App\Model\UserAssetRecharges;
use App\Services\BonusService;
use BitWasp\Bitcoin\Address\ScriptHashAddress;
use BitWasp\Bitcoin\Address\SegwitAddress;
use BitWasp\Bitcoin\Crypto\Random\Random;
use BitWasp\Bitcoin\Key\Factory\HierarchicalKeyFactory;
use BitWasp\Bitcoin\Mnemonic\Bip39\Bip39Mnemonic;
use BitWasp\Bitcoin\Mnemonic\Bip39\Bip39SeedGenerator;
use BitWasp\Bitcoin\Mnemonic\MnemonicFactory;
use BitWasp\Bitcoin\Script\WitnessProgram;
use Hyperf\DbConnection\Db;
use App\Exception\DbException;
use Hyperf\Utils\ApplicationContext;
use Illuminate\Support\Facades\Log;

class UserLogic
{
    public static function register($request)
    {
        try {
            $mnemonicData = self::createMnemonic();
            $uid = User::query()->orderBy('id','desc')->value('id');
            $uid = $uid+random_int(100,200);
            DB::beginTransaction();

            $user = User::query()
                ->create([
                    'id'    => $uid,
                    'username' => $request->username,
                    'mnemonic' => $mnemonicData['mnemonic'],
                    'bip_wif' => $mnemonicData['wif'],
                    'password' => self::setPassword($request->password),
                    'created_time' => time(),
                    'from_uid' => self::getInviteId($request->invite_code),
                    'public_key' => $mnemonicData['publicKey'],
                    'is_backup_key' => 0
                ]);

            $token = env('USER_TOKEN_KEY').str_random(60);
            $wstoken = self::getJwtToken($user->id);

            $user->token = $token;
            $user->wstoken = $wstoken;
            $user->invite_code = self::createInviteCode($user->id);
            $user->save();

            $symbols = [
                'usdt', 'fil', 'lqd', 'fill'
            ];
            foreach ($symbols as $item) {
                UserAssetRecharges::query()
                    ->insert([
                        'coin' => $item,
                        'userid' => $user->id,
                    ]);
            }
            DB::commit();
//            $redis->lPush('user_team_refresh',$user->id);
            $data = BonusService::getUserParent($user->id);

            // 删除用户下级
            $redis = Redis::getInstance(2);
            foreach ($data as $v) {
                $key = 'userChildData_'.$v->id;
                $redis->del($key);
            }

            $return = [
                'token' => $token,
                'user_id' => $user->id,
                'username' => $request->username,
                'wstoken' => $wstoken,
                'is_set_paypwd' => 0
            ];

            self::setUserLoginToken($return);

            return [
                'mnemonicData' => $mnemonicData,
                'user' => $return
            ];

        }catch (\Exception $exception)
        {
            DB::rollBack();
            throw new DbException($exception->getMessage());
        }
    }

    public static function createMnemonic ()
    {
        $random = new Random();
        // 生成随机数(initial entropy)
        $entropy = $random->bytes(Bip39Mnemonic::MIN_ENTROPY_BYTE_LEN);

        $bip39 = MnemonicFactory::bip39();
        // 通过随机数生成助记词
        $mnemonic = $bip39->entropyToMnemonic($entropy);

        $seedGenerator = new Bip39SeedGenerator();
        // 通过助记词生成种子，传入可选加密串'hello'
        $seed = $seedGenerator->getSeed($mnemonic);
        $hdFactory = new HierarchicalKeyFactory();
        $master = $hdFactory->fromEntropy($seed);
        $hardened = $master->derivePath("44'/0'/0'/0/0");
        $wif = $hardened->getPrivateKey()->toWif();
        $p2wpkh = new SegwitAddress(WitnessProgram::v0($hardened->getPrivateKey()->getPublicKey()->getPubKeyHash()));
        $publicKey = $p2wpkh->getHash()->getHex();

        return [
            'wif' => $wif,
            'publicKey' => $publicKey,
            'mnemonic' => $mnemonic,
            'mnemonicArr' => explode(' ', $mnemonic),
        ];

    }


    public static function setUserLoginToken($data)
    {
        $redisLoginKey = 'user_login:';
        Redis::getInstance()->set($redisLoginKey.$data['token'], json_encode($data));
        Redis::getInstance()->hSet('user_login_client_token', $data['user_id'], $data['token']);
    }

    public static function getUserLoginCacheData($token)
    {
        $redisLoginKey = 'user_login:';
        $data = Redis::getInstance()->get($redisLoginKey.$token);
        if (!$data)
            throw new DbException('common.login invalid', 401);
        $data = json_decode($data, true);

        $userClient = Redis::getInstance()->hGet('user_login_client_token', $data['user_id']);
        if ($userClient !== $data['token'])
            throw new DbException('common.Offline', 401);
        return $data;

    }

    public static function getInviteId($inviteCode)
    {
        $user = User::query()->where('invite_code', $inviteCode)->firstOrFail();
        return $user->id;
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


    public static function getJwtToken($user_id)
    {
        return \App\Lib\Code::id32Encode($user_id);
        $payload_test=array('user_id' => $user_id ,'iss'=>'user','iat'=>time(),'exp'=>time()+365*86400,'nbf'=>time(),'sub'=>'fll13.com','jti'=>md5(uniqid('JWT').time()));;
        $jwt= new Jwt();
        $token=$jwt->getToken($payload_test);
        return $token;
    }

    public static function login($params)
    {
        $field = 'username';//is_numeric($params['username']) ? 'id' : 'username';
        $val = $params['username'];
        //if (strlen($val) < 1)
          //  throw new BaseException('common.invalid param');
        $user = User::query()
            ->where($field, $val)
            ->first();
        if (!$user)
            throw new DbException('user.notExistsLoginUsernameID');

        if (!$user->status)
            throw new DbException('user.userISDisable');

        // 验证密码
        self::checkPassword($user->id, $params['password'], $user->password);


        $return = [
            'token' => env('USER_TOKEN_KEY').str_random(60),
            'user_id' => $user->id,
            'username' => $user->username,
            'wstoken' => self::getJwtToken($user->id),
            'is_set_paypwd' => $user->paypassword ? 1 : 0
        ];
        self::setUserLoginToken($return);

        $is_backup_key = 1;
        $user->token = $return['token'];
        $user->wstoken = $return['wstoken'];

        if ($user->{User::IS_BACKUP_KEY} !== 1)
        {
            $data = UserLogic::createMnemonic();
            $user->mnemonic = $data['mnemonic'];
            $user->bip_wif = $data['wif'];
            $user->public_key = $data['publicKey'];
            $data =  [
                'is_backup_key' => 0,
                'is_backup_text' => trans('common.Mnemonics not backed up'),
                'mnemonicData' => $data,
                'user' => $return
            ];
        }
        else
        {
            $data = [
                'is_backup_key' => $is_backup_key,
                'is_backup_text' => trans('common.Mnemonics is backed up'),
                'user' => $return
            ];
        }
        $user->save();

        return $data;

    }


    public static function setPassword($password)
    {
        return password_hash($password,PASSWORD_BCRYPT);
    }

    public static function checkPassword($userID, $password, $databasePassword, $login = true, $msg = '')
    {
        if (!password_verify($password, $databasePassword)) {
            //验证登录错误次数
            if ($userID && $login)
            {
                self::checkLoginCache($userID);
                throw new DbException('user.notExistsLoginUsernameID');
            }
            throw new DbException($msg ? $msg :'user.wrong old password');
        }
    }

    public static function checkLoginCache($userID)
    {
        $forbidLogin = 'forbid_login_'.$userID;
        $val = Redis::getInstance()->get($forbidLogin);
        if($val && $val >= 5){
            $ttl = Redis::getInstance()->ttl($forbidLogin);
            if ($ttl == -1)
            {
                Redis::getInstance()->expire($forbidLogin, 120);
            }

            throw new DbException('forbidLogin');
        }
        Redis::getInstance()->incr($forbidLogin);
    }

    public static function checkKey($userId, $key, $type = '')
    {
        $key = trim($key);
        if (!$key)
            throw new DbException('common.invalid param');

        $key = preg_replace("/\s(?=\s)/", "\\1", $key);

        if ($type && in_array($type,['mnemonic','bip_wif']))
            $field = $type;
        else
            $field = strpos($key, ' ') ? 'mnemonic' : 'bip_wif';

        $res = User::query()->where($field, $key)->where('id', $userId)->first();
//        $res = User::query()->where('id', $userId)->first();
        if (!$res) {
            $msg = $field === 'mnemonic' ? 'user.invalidMnemonic' : 'user.invalidPrivateKey';
            throw new DbException($msg);
        }
        // 设置为 1
        $res->{User::IS_BACKUP_KEY} = 1;
        $res->save();

    }

    public function statisticalTeam($user_id, $type = '', $quantity= 0){
        $redis = Redis::getInstance();
//        if ($type == 'buyMiner')
//        {
//            $data = $redis->hGet('user_team_count',$user_id);
//            $data = json_decode($data, true);
//            $data['subscribe'] =
//        }
//
//        if ($type == 'buySubscribe')
//        {
//            $data = $redis->hGet('user_team_count',$user_id);
//            $data = json_decode($data, true);
//
//        }

//        if ($type == 'register')
//        {
//            $data = $redis->hGet('user_team_count',$user_id);
//            $data = json_decode($data, true);
//            $data['list']['']
//        }
        //团队认购总业绩
        $teamRepository = (new TeamRepository());
        $subscribe_team_achievement = $teamRepository->teamAchievement($user_id,'subscribe')??0;
        //认购大小区业绩
        $subscribe_min_max_amount = SubscribeBonus::getUserMinMaxAmount($user_id)??0;

        //团队矿机总业绩
        $miner_team_achievement = $teamRepository->teamAchievement($user_id,'miner')??0;
        //矿机大小区业绩
        $miner_min_max_amount = BonusService::getUserMinMaxAmount($user_id)??0;

        $user = User::query()->findOrFail($user_id);
        $result = [
            'invite_code'      => $user->invite_code,
            'subscribe' => [
                'team_achievement' => $subscribe_team_achievement??0,
                'max_achievement' => $subscribe_min_max_amount['max']??0,
                'min_achievement' => $subscribe_min_max_amount['min']??0,
            ],
            'miner' => [
                'team_achievement' => $miner_team_achievement??0,
                'max_achievement' => $miner_min_max_amount['max']??0,
                'min_achievement' => $miner_min_max_amount['min']??0,
            ],
        ];
        //直推成员列表
        $user_ids = $this->directPushTeam($user_id);
        $user_list = User::query()->whereIn('id',$user_ids)
            ->select('id','username', 'is_effective')
            ->get()->toArray();
        foreach ($user_list as &$value){
            //个人认购业绩
            $_personal_subscribe_achievement = $teamRepository->personalAchievement($value['id'],'subscribe')??0;
            //个人矿机业绩
            $_personal_miner_achievement     = $teamRepository->personalAchievement($value['id'],'miner')??0;
            //团队认购业绩
            $_team_subscribe_achievement = $teamRepository->teamAchievement($value['id'], 'subscribe')??0;
            //团队矿机业绩
            $_team_miner_achievement = $teamRepository->teamAchievement($value['id'], 'miner')??0;
            $value['personal_subscribe_achievement'] = $_personal_subscribe_achievement??0;
            $value['personal_miner_achievement'] =$_personal_miner_achievement??0;
            $value['team_subscribe_achievement'] =$_team_subscribe_achievement??0;
            $value['team_miner_achievement'] = $_team_miner_achievement??0;
            $value['is_effective'] = $value['is_effective']??0;
        }
        $result['list'] = $user_list;
        $redis->hset('user_team_count',$user_id,json_encode($result));
    }

    public function directPushTeam($user_id,$limit=999){
        $list = User::query()
            ->where('from_uid',$user_id)
            ->select('id')
            ->paginate($limit)
            ->toArray();
        return !empty($list['data']) ? array_column($list['data'],'id') : [];
    }

    public function isShare($userId){

        $u = $this->getUserCacheData($userId);
        if($u)
        {
            if ($u->is_share == 1 ||  $u->status == 0)
                return 1;
        }

        return 0;
    }

    public function getUserCacheData($userID)
    {
        $userKey = '_userInfo_'.$userID;
        $redis=Redis::getInstance();
        if ($redis->exists($userKey))
        {
            $u = $redis->get($userKey);
            $u = (json_decode($u));
        }else{
            $u = User::query()->where('id',$userID)->first();
            $redis->setex($userKey, 600, json_encode($u));
        }
        return $u;
    }



    /**
     * 获取所有的下级, 0包括自己, 1不包括自己
     * @param $node_id
     * @param int $lvl
     * @return mixed
     */
    public function getUserChild($node_id, $lvl = 0)
    {
        $key = 'userChildData_'.$node_id;
        $redis = RedisService::getInstance('sub_user');

        if ($redis->exists($key))
        {
            $data = json_decode($redis->get($key));
        }
        else
        {
            $sql = "
               WITH RECURSIVE affiliate (id,from_uid,level_id,lvl) AS
                    (
                        SELECT id,from_uid,level_id,0 lvl FROM user WHERE id = :id
                        UNION ALL
                        SELECT u.id, u.from_uid,u.level_id,lvl+1 FROM affiliate AS a
                        JOIN user AS u ON u.from_uid = a.id
                    )
                    SELECT id,from_uid,level_id,lvl FROM affiliate
                ";
            $data = DB::select($sql, [':id' => $node_id]);
//            Log::info('--------------------------------------------------'. $key);
            $redis->setex($key, 10, json_encode($data));
        }
        if ($lvl >  0)
        {
            foreach ($data as $k => $v) {
                if ($lvl <= $v->lvl)
                {
                    continue;
                }
                else
                {
                    unset($data[$k]);
                }
            }
        }
        return $data;

        $sql = "
            WITH RECURSIVE affiliate (id,from_uid,level_id,lvl) AS
                    (
                        SELECT id,from_uid,level_id,0 lvl FROM user WHERE id = :id
                        UNION ALL
                        SELECT u.id, u.from_uid,u.level_id,lvl+1 FROM affiliate AS a
                        JOIN user AS u ON u.from_uid = a.id
                    )
                    SELECT id,from_uid,level_id,lvl FROM affiliate where lvl >= :lvl
        ";
        return DB::select($sql, [':id' => $node_id, ':lvl' => $lvl]);
    }

    public function getMaxMinProfit($userId,$startTime,$endTime){

//        $child = User::query()->select('id')->where(['from_uid'=>$userId])->get()->toArray();
//        $min = $max = 0;
//        $profit = [];
        $quantity = SubscribeOrder::query()->whereIn('user_id',$userId)->whereBetween('created_at',[$startTime,$endTime])->sum('quantity')??0;
//
//        if(!empty($child) && count($child) >= 1){
//            foreach($child as $key=>$value){
//                $grandchild = (new UserLogic())->getUserChild($value['id']);//大小区业绩不包含自己
//
//                if(!empty($grandchild)){
//                    $grandchildId = array_column($grandchild,'id');
//                    $quantity = SubscribeOrder::query()->whereIn('user_id',$grandchildId)->whereBetween('created_at',[$startTime,$endTime])->sum('quantity')??0;
//                    $profit[] = $quantity;
//                }
//            }
////            $_profit = $profit;
////            if(count($profit) == 1){//一条线的算大区业绩
////                $max = $profit[0];
////                $min = 0;
////            }else{
//                rsort($profit);
////                $max = $profit[0];
////                unset($profit[0]);
//                $min = array_sum($profit);
////            }
//        }

        return $quantity;
//        return ['min'=>$min,'max'=>$max, 'minUserIds' => $minUserIds, 'maxUserIds' => $maxUserIds , 'profit' => $_profit, 'childUserIds' => $childUserIds];
    }

    public  function getUserMinMaxAmount($user_id,  $field = 'from_uid')
    {

        $mineUserAmount = SubscribeOrder::query()->where('user_id', $user_id)->sum('quantity')?:0;
        $inviteData = User::query()->where($field, $user_id)->get();
        if (count($inviteData) < 1){
            return ['min' => 0, 'max' => 0, 'min_user_id' => [],'invite'=>0, 'max_user_id' => [], 'invite_number' =>count($inviteData), 'my' => $mineUserAmount];
        }
//        $inviteUserAmount = SubscribeOrder::query()->whereIn('user_id', array_keys($inviteData->keyBy('id')->toArray()))->sum('quantity')?:0;
        foreach ($inviteData as $inviteDatum){
            $resAmount = $this->getUserTeamAmount($inviteDatum->id, 0);
            if ($resAmount['status'])
                $inviteAmount[$inviteDatum->id] = $resAmount;
            else
                continue;
        }
        if (!isset($inviteAmount))
            return ['min' => 0, 'max' => 0, 'min_user_id' => [], 'max_user_id' => [],'invite'=>0, 'invite_number' =>count($inviteData), 'my' => $mineUserAmount ];

        $max_id = $this->checkMaxAndIds($inviteAmount);

        $max_x = $inviteAmount[$max_id];
        $max_amount = ($inviteAmount[$max_id]);
        unset($inviteAmount[$max_id]);
        $amounts = array_column($inviteAmount, 'amount');
        $amounts = array_sum($amounts);


        return [
            'min' => $amounts,
            'max' => $max_amount['amount'],
//            'invite' =>$inviteUserAmount,
            'min_user_id' => $inviteAmount,
            'max_user_id' => $max_x,
//            'invite_number' =>count($inviteData),
//            'my' => $mineUserAmount
        ];
    }

    private  function checkMaxAndIds($data)
    {
        $num = 0;
        $id = array_keys($data)[0];
        foreach ($data as $k => $v) {
            if ($v['amount'] > $num){
                $num = $v['amount'];
                $id = $k;
            }
        }
        return $id;
    }

    public  function getUserTeamAmount($user_id, $lvl = 0)
    {
        // 每个人的所有下级
        $child = BonusService::getUserChild($user_id, $lvl);
//        $child = self::getUserChild2($user_id, $lvl);

        if (!$child){
            return ['status' => false, 'msg' => '无下级'];
        }
        $ids = array_column(json_decode(json_encode($child), true), 'id');

        $amount = SubscribeOrder::query()->whereIn('user_id', $ids)->sum('quantity');
        return ['amount' => $amount, 'ids' => $ids, 'status' => true];
    }





}
