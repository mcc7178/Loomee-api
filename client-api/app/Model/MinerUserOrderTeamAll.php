<?php

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class MinerUserOrderTeamAll extends Model
{
    protected $table = 'miner_user_order_team_all';

    public $timestamps = false;

    protected $fillable = [
        'order_num',
        'user_id',
        'miner_level_id',
        'node_profit',
        'big_node_profit',
        'super_node_profit',
        'quantity',
        'date',
        'award_date',
        'award_remain_day',
        'manage_fee',
        'node_nums',
        'type',
    ];
    
    
    
}
