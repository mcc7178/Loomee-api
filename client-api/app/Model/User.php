<?php
namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class User extends Model
{
    protected $table = 'user';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'nickname',
        'username',
        'mnemonic',
        'bip_wif',
        'password',
        'created_time',
        'from_uid',
        'is_backup_key',
        'public_key',
        'is_look_asset',
        'is_share'
    ];


    const IS_BACKUP_KEY = 'is_backup_key';

    public function subscribe_team_conf()
    {
        return $this->hasOne(SubscribeTeamConf::class,'id','subscribe_team_id');
    }

    public function subscribe_node_conf()
    {
        return $this->hasOne(SubscribeNodeConf::class,'id','subscribe_node_id');
    }

    public function miner_level_conf()
    {
        return $this->hasOne(MinerLevelConf::class,'id','miner_level_id');
    }

    public function user_asset_recharges()
    {
        return $this->hasMany(UserAssetRecharges::class,'userid','id');
    }

}
