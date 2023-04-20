<?php

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class Withdraw extends Model
{
    protected $table = 'withdraw';
    public $timestamps = false;

    const EN = 'en';
    const ZH = 'zh-CN';

    protected $appends = [
        'status_en'
    ];

    const STATUS = [
        self::STATUS_FAIL => '失败',
        self::STATUS_WAIT => '等待',
        self::STATUS_PROCESSED => '区块确认中',
        self::STATUS_SUCCESS => '成功',
    ];

    const STATUS_SUCCESS = 'success';
    const STATUS_WAIT = 'wait';
    const STATUS_FAIL = 'fail';
    const STATUS_PROCESSED = 'processing';


    protected $fillable = [
        'userid',
        'address',
        'coin',
        'txid',
        'number',
        'fee',
        'hash',
        'amount',
        'created_at',
        'updated_time',
        'status',
        'remark',
        'receive_data',
        'note',
        'admin_id',
        'audit_time',
        'audit_result',
        'chain_id',
        'type',
    ];

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo('App\Models\User', 'userid');
    }

    const STATUS_LIST = [
        'wait' => '处理中',
        'success' => '完成',
        'cancel' => '已撤销',
        'process' => '区块确认中',
        'nopass' => '提币失败'
    ];

    public function getStatusAttribute($val)
    {
        if (app('translator')->getLocale() == self::EN) {
            return $val;
        }
        return self::STATUS_LIST[$val];
    }

    public function getStatusEnAttribute()
    {
        return $this->attributes['status'];

    }

    public function coins()
    {
        return $this->belongsTo('App\Models\Coins', 'coin', 'symbol');
    }


}
