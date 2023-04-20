<?php

namespace App\Model;

//use Laravel\Lumen\Routing\UrlGenerator;
use Hyperf\Database\Model\Model;

class TransferLog extends Model
{
    protected $table = 'transfer_log';

    public $timestamps = false;

    protected $guarded = [];

    public function to_user()
    {
        return $this->hasOne(User::class, 'id', 'to_user_id');
    }

    public function getCreatedAtAttribute($val)
    {
        return is_numeric($val) ? date('Y-m-d H:i:s', $val) : $val;
    }
}
