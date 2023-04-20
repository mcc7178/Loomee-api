<?php


namespace App\Model;

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class SubscribeOrderReleaseLog extends Model
{
    protected $table = 'subscribe_order_release_log';
    public $timestamps = false;

    public function getCreatedAtAttribute($val)
    {
        return date("Y-m-d H:i:s",$val);
    }

}
