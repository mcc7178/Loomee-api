<?php

namespace App\Model;
use Hyperf\DbConnection\Model\Model;

class FinanceLog extends Model
{
    protected $table = 'finance_log';

    public $timestamps = false;

    protected $guarded = [];

    const EN = 'en';

    protected $fillable = [
        'userid',
        'coin',
        'old_quantity',
        'old_freeze',
        'new_quantity',
        'new_freeze',
        'quantity',
        'freeze',
        'behavior',
        'behavior_id',
        'remark',
        'created_at',
        'status',
        'account',
    ];

    const EZH_BEHAVIOR = [
        'recharge' => '充币',
        'withdraw' => '提币',
        'exchange' => '闪兑',
        'subscribe' => '认购',
        'transfer' => '转账',

        'subscribe_self_bonus' => '认购静态收益',
        'subscribe_weighted_bonus' => '认购节点收益',
        'subscribe_team_bonus' => '认购团队级差收益',
        'subscribe_invite_bonus' => '认购推荐奖励',
        'subscribe_release_bonus' => '认购直推奖励',

        'miner_static_bonus' => '矿机静态挖矿收益',
        'miner_recommend' => '矿机推荐奖励',
        'miner_release_bonus' => '矿机直推奖励',
        'miner_team_bonus' => '矿机团队加权奖励',
        'miner_all_bonus' => '矿机全网加权奖励',

        'subscribe_start_bonus' => '英雄日榜奖励',

        'subscribe_equal_bonus' => '平级收益',
        'miner' => '购买矿机',
        'system' => '调节资金',
        'trade' => '委托',
    ];

    const EN_BEHAVIOR = [
        'recharge' => 'Charge money',
        'withdraw' => 'Withdraw money',
        'exchange' => 'Flash cash',
        'subscribe' => 'Subscription',
        'transfer' => 'Transfer accounts',

        'subscribe_self_bonus' => 'Subscription static income',
        'subscribe_weighted_bonus' => 'Subscription node income',
        'subscribe_team_bonus' => 'Differential income of subscription team',
        'subscribe_invite_bonus' => 'Subscription recommendation Award',
        'subscribe_release_bonus' => 'Subscription direct incentive',

        'miner_static_bonus' => 'Static mining profit of mining machine',
        'miner_recommend' => 'Recommended reward for mining machines',
        'miner_release_bonus' => 'Direct push reward of mine machine',
        'miner_team_bonus' => 'Weighted reward for mining machinery Team',
        'miner_all_bonus' => 'Mining machinery whole network weighted reward',

        'subscribe_start_bonus' => 'Hero day list Award',

        'subscribe_equal_bonus' => 'Flat income',
        'miner' => 'Purchase miner',
        'system' => 'Adjusting funds',
        'trade' => 'Entrust',
    ];

    const EZH_STATUS = [
        0 => '处理中',
        1 => '成功',
        2 => '失败'
    ];

    const EN_STATUS = [
        0 => 'In process',
        1 => 'Success',
        2 => 'Fail'
    ];

    public function getBehaviorAttribute($val)
    {
        //todo 环境变量配置
//        if (app('translator')->getLocale() == self::EN)
//            return self::EN_BEHAVIOR[$val] ?? '';
        return self::EZH_BEHAVIOR[$val] ?? '';
    }

    public function getStatusAttribute($val)
    {
        //todo 环境变量配置
//        if (app('translator')->getLocale() == self::EN)
//            return self::EN_STATUS[$val] ?? '';
        return self::EZH_STATUS[$val] ?? '';
    }

    public function getCreatedAtAttribute($val)
    {
        return is_numeric($val) ? date('Y-m-d H:i:s', $val) : $val;
    }
}
