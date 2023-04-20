<?php


namespace App\Model;


use Hyperf\Database\Model\Model;

class SubscribeTeamConf extends Model
{
    protected $table = 'subscribe_team_conf';

    const UPDATED_AT = null;

    protected $fillable = [
        'name',
        'performance_min',
        'performance_max',
        'static_bonus'
    ];
}
