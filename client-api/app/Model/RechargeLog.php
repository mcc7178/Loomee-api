<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class RechargeLog extends Model
{
    protected $table = 'recharge_log';
    public $timestamps = false;

    protected $guarded =[];

    protected $casts = [
        'time' => 'datetime',

    ];

    const EN = 'en';
    const ZH = 'zh-CN';

    const STATUS_LIST = [
        'finish'        => '已完成',
        'inexistence'   => '完成',
    ];


    public function user()
    {
	    return $this->belongsTo('App\Models\User', 'userid');
	}

    public function coins()
    {
	    return $this->belongsTo('App\Models\Coins', 'coin', 'symbol');
	}

    public function getStatusAttribute($val)
    {
        if( app('translator')->getLocale() == self::EN ) {
            return $val;
        }
        return self::STATUS_LIST[$val];
    }
}
