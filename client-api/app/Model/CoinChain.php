<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Laravel\Lumen\Routing\UrlGenerator;


class CoinChain extends Model
{
    protected $table = 'coin_chain';
    public $timestamps = false;

    protected $guarded =[];

    const RECHARGE_STATUS = 'is_recharge';
    const WITHDRAW_STATUS = 'is_out';

    const RECHARGE_Y = '1';
    const WITHDRAW_Y = '1';

    const NOT_RECHARGE = 0;
    const CAN_RECHARGE = 1;

//    protected $appends = [
//        'recharge_notice',
//        'withdraw_notice'
//    ];

//    protected $hidden  = [
////        'zh_recharge_notice',
////        'en_recharge_notice',
////        'zh_withdraw_notice',
////        'en_withdraw_notice',
//        'auto_out_single',
//        'out_day_max',
//        'min_recharge',
//        'recharge_notice_status',
//        'withdraw_notice_status',
//        'is_auto_out',
//        'auto_out_single',
//        'auto_out_daily',
//        'chain_id',
//        'out_fee_limit',
//        'out_fee_ratio',
//        'out_min',
//        'out_max',
//        'status',
//        'is_out',
//    ];

    const EN = 'en';
    const EN_RECHARGE_NOTICE = 'en_recharge_notice';
    const EN_WITHDRAW_NOTICE = 'en_withdraw_notice';

    const ZH = 'zh-CN';
    const ZH_RECHARGE_NOTICE = 'zh_recharge_notice';
    const ZH_WITHDRAW_NOTICE = 'zh_withdraw_notice';

    public function getRechargeNoticeAttribute()
    {
        if( app('translator')->getLocale() == self::EN )
            return  $this->attributes[self::EN_RECHARGE_NOTICE];

        return  $this->attributes[self::ZH_RECHARGE_NOTICE];
    }

    public function getWithdrawNoticeAttribute()
    {
        if( app('translator')->getLocale() == self::EN )
            return  $this->attributes[self::EN_WITHDRAW_NOTICE];
        return  $this->attributes[self::ZH_WITHDRAW_NOTICE];
    }



}
