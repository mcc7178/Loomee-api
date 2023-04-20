<?php

namespace App\Services;

use App\Model\MemberLevel;
use App\Model\MinerLevelConf;
use App\Model\MinerUserOrder;
use App\Model\SubscribeTeamConf;
use App\Model\User;
use App\Model\UserAssetRecharges;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Utils\ApplicationContext;
use App\Utils\Redis;

class BonusService
{
    /**
     * @Inject()
     * @var StdoutLoggerInterface
     */
    private static $logger;

    /**
     * 获取小区、大区业绩
     * @param int $user_id 用户id
     * @param bool $getChildIds true=只获取小区用户id
     * @return array
     */
    public static function getUserMinMaxAmount($user_id, $field = 'from_uid')
    {

        $mineUserAmount = MinerUserOrder::query()->where('user_id', $user_id)->sum('quantity') ?: 0;
        $inviteData = User::query()->where($field, $user_id)->get();

        if (count($inviteData) < 1) {
            return ['min' => 0, 'max' => 0, 'min_user_id' => [], 'invite' => 0, 'max_user_id' => [], 'invite_number' => count($inviteData), 'my' => $mineUserAmount];
        }
        $inviteUserAmount = MinerUserOrder::query()->whereIn('user_id', array_keys($inviteData->keyBy('id')->toArray()))->sum('quantity') ?: 0;
        foreach ($inviteData as $inviteDatum) {
            $resAmount = self::getUserTeamAmount($inviteDatum->id, 0);
            if ($resAmount['status'])
                $inviteAmount[$inviteDatum->id] = $resAmount;
            else
                continue;
        }
        if (!isset($inviteAmount))
            return ['min' => 0, 'max' => 0, 'min_user_id' => [], 'max_user_id' => [], 'invite' => 0, 'invite_number' => count($inviteData), 'my' => $mineUserAmount];
        $max_id = self::checkMaxAndIds($inviteAmount);

        $max_x = $inviteAmount[$max_id];
        $max_amount = ($inviteAmount[$max_id]);
        unset($inviteAmount[$max_id]);
        $amounts = array_column($inviteAmount, 'amount');
        $amounts = array_sum($amounts);


        return [
            'min' => $amounts,
            'max' => $max_amount['amount'],
            'invite' => $inviteUserAmount,
            'min_user_id' => $inviteAmount,
            'max_user_id' => $max_x,
            'invite_number' => count($inviteData),
            'my' => $mineUserAmount
        ];
    }


    /**
     * 获取最大区的id
     * @param $data
     * @return int|string
     */
    private static function checkMaxAndIds($data)
    {
        $num = 0;
        $id = array_keys($data)[0];
        foreach ($data as $k => $v) {
            if ($v['amount'] > $num) {
                $num = $v['amount'];
                $id = $k;
            }
        }
        return $id;
    }

    public static function getUserMaxAsset($data)
    {
        if (!$data)
            return 0;
        $num = $id = 0;
        foreach ($data as $user_id => $sum) {
            if ($sum > $num) {
                $id = $user_id;
                $num = $sum;
            }
        }
        return $id;
    }

    /* 传递数值判断是否可以提升认购等级 */
    public static function getUserLv($quantify)
    {
        $item = SubscribeTeamConf::query()->orderBy('performance_min', 'desc')->select('id', 'performance_min')->get();
        if (!empty($item)) {
            foreach ($item as $key => $value) {
                if ($quantify >= $value['performance_min']) {
                    return $value['id'];
                }
            }
        }
        //返回0不做处理
        return 0;
    }

    /* 传递数值判断是否可以提升矿机认购等级
     * $invite  直推业绩
     * $community   小区业绩
     */
    public static function getUserMinerLv($invite, $community)
    {
        $item = MinerLevelConf::orderBy('community_performance', 'desc')->select('id', 'invite_performance', 'community_performance')->get();
        if (!empty($item)) {
            foreach ($item as $key => $value) {
                if ($invite >= $value['invite_performance'] && $community >= $value['community_performance']) {
                    return $value['id'];
                }
            }
        }
        //返回0不做处理
        return 0;
    }

    /**
     * 获取所有的上级, 0包括自己, 1不包括自己
     * @param $node_id
     * @param int $lvl
     * @return mixed
     */
    public static function getUserParent($node_id, $lvl = 0)
    {
        $key = 'userParentData_' . $node_id;
        $redis = Redis::getInstance();
        if ($redis->exists($key)) {
            $data = json_decode($redis->get($key));
        } else {
            $sql = "
               WITH RECURSIVE affiliate (id,from_uid,subscribe_team_id,lvl) AS
                        (
                            SELECT id,from_uid,subscribe_team_id,0 lvl FROM user WHERE id = {$node_id}
                            UNION ALL
                                SELECT u.id, u.from_uid,u.subscribe_team_id,lvl+1 FROM affiliate AS a
                            JOIN user AS u ON a.from_uid = u.id
                        )
               SELECT id,from_uid,subscribe_team_id,lvl FROM affiliate
            ";
            $data = Db::select($sql, [':lvl' => $lvl]);
            $redis->setex($key, 120, json_encode($data));
        }
        if ($lvl > 0) {
            foreach ($data as $k => $v) {
                if ($lvl <= $v->lvl) {
                    continue;
                } else {
                    unset($data[$k]);
                }
            }
        }
        return $data;
    }


    /**
     * 获取所有的下级, 0包括自己, 1不包括自己
     * @param $node_id
     * @param int $lvl
     * @return mixed
     */
    public static function getUserChild($node_id, $lvl = 0)
    {
        $key = 'userChildData_'.$node_id;
        $redis = Redis::getInstance();
        if ($redis->exists($key))
        {
            $data = json_decode($redis->get($key));
        }else{
            $sql = "
                WITH RECURSIVE affiliate (id,from_uid,lvl) AS
                        (
                            SELECT id,from_uid,0 lvl FROM user WHERE id = :id
                            UNION ALL
                            SELECT u.id, u.from_uid,lvl+1 FROM affiliate AS a
                            JOIN user AS u ON u.from_uid = a.id
                        )
                        SELECT id,lvl FROM affiliate 
                ";
            //上面sql优化为下面的t修改
            // $sql="select user_id as id,level+1 as lvl from member_level where pid=:id order by level asc";
            $data = Db::select($sql, [':id' => $node_id]);
            array_unshift($data,["id"=>$node_id,"lvl"=> 0]);

            $redis->setex($key, 60,json_encode($data));
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
    }

    public static function getUserChild2222($node_id, $lvl = 0)
    {
        return '';
        $key = 'userChildData_' . $node_id;
        $redis = Redis::getInstance('index2');
        if ($redis->exists($key)) {
//            var_dump('获取下级 - redis');
            $data = json_decode($redis->get($key));
        } else {
//            var_dump('获取下级 - mysql');
            $sql = "
                WITH RECURSIVE affiliate (id,from_uid,lvl) AS
                        (
                            SELECT id,from_uid,0 lvl FROM user WHERE id = :id
                            UNION ALL
                            SELECT u.id, u.from_uid,lvl+1 FROM affiliate AS a
                            JOIN user AS u ON u.from_uid = a.id
                        )
                        SELECT id,lvl FROM affiliate
                ";
            $data = Db::select($sql, [':id' => $node_id]);
            $redis->set($key, json_encode($data));
        }
        if ($lvl > 0) {
            foreach ($data as $k => $v) {
                if ($lvl <= $v->lvl) {
                    continue;
                } else {
                    unset($data[$k]);
                }
            }
        }
        return $data;
    }

    /**
     * 分页获取所有的下级, 0包括自己, 1不包括自己
     * @param $node_id
     * @param int $lvl
     * @param int $page
     * @param int $limit
     * @return mixed
     */
    public static function getUserChildPaginate($node_id, $lvl = 0, $page = 0, $limit = 10)
    {
        $page = $page > 0 ? $page - 1 : 0;
        $start = $page * $limit;
        $sql = "
            WITH RECURSIVE affiliate (id,from_uid,lvl) AS
                    (
                        SELECT id,from_uid,0 lvl FROM user WHERE id = :id
                        UNION ALL
                        SELECT u.id, u.from_uid,lvl+1 FROM affiliate AS a
                        JOIN user AS u ON u.from_uid = a.id
                    )
                    SELECT id,lvl FROM affiliate where lvl >= :lvl limit :start,:limit
        ";
        return Db::select($sql, [':id' => $node_id, ':lvl' => $lvl, ':start' => $start, ':limit' => $limit]);
    }

    /**
     * 获取所有的下级数量, 0包括自己, 1不包括自己
     * @param $node_id
     * @param int $lvl
     * @return int
     */
    public static function getUserChildCount($node_id, $lvl = 0)
    {
        $sql = "
            WITH RECURSIVE affiliate (id,from_uid,lvl) AS
                    (
                        SELECT id,from_uid,0 lvl FROM user WHERE id = :id
                        UNION ALL
                        SELECT u.id, u.from_uid,lvl+1 FROM affiliate AS a
                        JOIN user AS u ON u.from_uid = a.id
                    )
                    SELECT COUNT(1) AS team_count FROM affiliate where lvl >= :lvl
        ";
        $result = Db::select($sql, [':id' => $node_id, ':lvl' => $lvl]);
        // $res=Db::table('member_level')->where('pid',$node_id)->orderBy('level','asc')->count();
        return !empty($result[0]) ? $result[0]->team_count : 0;
        //上面sql优化为下面的t修改
        // $count=Db::table('member_level')->where('pid',$node_id)->orderBy('level','asc')->count();
        // return $count+1;
    }

    /**
     * 获取单人团队总业绩
     * @param $user_id
     * @param int $lvl
     * @return array
     */
    public static function getUserTeamAmount($user_id, $lvl = 0)
    {
        // 每个人的所有下级
        $child = self::getUserChild($user_id, $lvl);

        if (!$child) {
            return ['status' => false, 'msg' => '无下级'];
        }
        $ids = array_column(json_decode(json_encode($child), true), 'id');

        $amount = MinerUserOrder::query()->whereIn('user_id', $ids)->sum('quantity');


        return ['amount' => $amount, 'ids' => $ids, 'status' => true];
    }

    public static function getUserTeamAssetAmount($user_id, $coin, $lvl = 0)
    {
        // 每个人的所有下级
        $child = self::getUserChild($user_id, $lvl);
        if (!$child) {
            return ['status' => false, 'msg' => '无下级'];
        }
        $ids = array_column(json_decode(json_encode($child), true), 'id');

        //
        $amount = UserAssetRecharges::query()->whereIn('userid', $ids)->where('coin', $coin)->sum('quantity');

        return ['amount' => $amount, 'ids' => $ids, 'status' => true];
    }

    /**
     * 检测用户在不在某个上级 大区 或 小区
     * @param $user_id
     * @param $node_id
     * @param string $type
     * @return array
     */
    public static function childBlock($user_id, $node_id, $type = 'count')
    {
        // 获取大区
        // 大区等于： 所有的直推的总业绩
        $inviteData = User::query()->where('node_id', $node_id)->get();

        if ($inviteData->isEmpty()) {
            return ['status' => false, 'msg' => '无直推'];
        }

        $inviteAmount = [];
        if (count($inviteData) <= 1)
            return ['status' => false, 'msg' => '无小区'];

        foreach ($inviteData as $inviteDatum) {
            $resAmount = self::getUserTeamAmount($inviteDatum->id, 0, $type);
            if ($resAmount['status'])
                $inviteAmount[$inviteDatum->id] = $resAmount;
            else
                continue;
        }

        $max_id = self::checkMaxAndIds($inviteAmount);
//        $_amounts = array_sum(array_column($inviteAmount, 'convert_quantity')) ;
        unset($inviteAmount[$max_id]);

        // 如果这个ID 存在这些小区内
        $status = false;
        foreach ($inviteAmount as $k => $v) {
            if (in_array($user_id, $v['ids'])) {
                $status = true;
                break;
            }
        }
        if (!$status) {
            return ['status' => false, 'msg' => '不在小区'];
        }
        $amounts = array_column($inviteAmount, 'amount');
        $amounts = array_sum($amounts);

        return ['status' => true, 'msg' => 'ok', 'amount' => $amounts];
    }

    /**
     * 获取上级
     * @param int $user_id 当前用户
     * @param int $lvl 获取的层数
     * @return mixed
     */
    public static function getParentData($user_id, $minLvl = 0)
    {
        $sql = "
            WITH RECURSIVE affiliate (id,node_id,lvl) AS
                    (
                        SELECT id,node_id,0 lvl FROM user WHERE id = :id
                        UNION ALL
                        SELECT u.id, u.node_id,lvl+1 FROM affiliate AS a
                        JOIN user AS u ON a.node_id = u.id
                    )
                    SELECT id,node_id,lvl FROM affiliate where lvl >= :lvl
       ";
        return Db::select($sql, [':id' => $user_id, ':lvl' => $minLvl]);
    }


    public static function getFromParentData($user_id, $minLvl = 0)
    {
        $sql = "
            WITH RECURSIVE affiliate (id,from_uid,lvl,node_level_id,miner_level_id) AS
                    (
                        SELECT id,from_uid,0 lvl,node_level_id,miner_level_id FROM user WHERE id = :id
                        UNION ALL
                        SELECT u.id, u.from_uid,lvl+1,u.node_level_id,u.miner_level_id FROM affiliate AS a
                        JOIN user AS u ON a.from_uid = u.id
                    )
                    SELECT id,from_uid,lvl,node_level_id,miner_level_id FROM affiliate where lvl >= :lvl
       ";
        return Db::select($sql, [':id' => $user_id, ':lvl' => $minLvl]);
    }

    public static function getEffectiveChildUser($user_id)
    {
        $data = User::query()->where([
            'from_uid' => $user_id,
            'activation_status' => 1
        ])->get();

        return [
            'data' => $data,
            'count' => count($data)
        ];
    }

    /**
     * 获取所有的层级下级
     * @param $node_id
     * @return array
     */
    public static function getNodeUserChild($node_id, $lvl = 1)
    {
        $sql = "
            WITH RECURSIVE affiliate (id,node_id,lvl) AS
                    (
                        SELECT id,node_id,0 lvl FROM user WHERE id = :id
                        UNION ALL
                        SELECT u.id, u.node_id,lvl+1 FROM affiliate AS a
                        JOIN user AS u ON u.node_id = a.id
                    )
                    SELECT id,lvl,node_id FROM affiliate where lvl >= :lvl
        ";
        return Db::select($sql, [':id' => $node_id, ':lvl' => $lvl]);
    }

    /**
     * 获取所有的层级下级 带用户名
     * @param $node_id
     * @return array
     */
    public static function getNodeUserChildWithName($node_id, $lvl = 1)
    {
        $sql = "
            WITH RECURSIVE affiliate (id,node_id,username,lvl) AS
                    (
                        SELECT id,node_id,username,0 lvl FROM user WHERE id = :id
                        UNION ALL
                        SELECT u.id, u.node_id,u.username,lvl+1 FROM affiliate AS a
                        JOIN user AS u ON u.node_id = a.id
                    )
                    SELECT id,lvl,node_id,username FROM affiliate where lvl >= :lvl
        ";
        return Db::select($sql, [':id' => $node_id, ':lvl' => $lvl]);
    }

    /**
     * 获取所有的下级, 包括自己
     * @param $node_id
     */
    public static function getUserChildWithName($node_id, $lvl = 0)
    {
        $sql = "
            WITH RECURSIVE affiliate (id,from_uid,username,lvl) AS
                    (
                        SELECT id,from_uid,username,0 lvl FROM user WHERE id = :id
                        UNION ALL
                        SELECT u.id, u.from_uid,u.username,lvl+1 FROM affiliate AS a
                        JOIN user AS u ON u.from_uid = a.id
                    )
                    SELECT id,lvl ,username FROM affiliate where lvl >= :lvl
        ";
        return Db::select($sql, [':id' => $node_id, ':lvl' => $lvl]);
    }
}
