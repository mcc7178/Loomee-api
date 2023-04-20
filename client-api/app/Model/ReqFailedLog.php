<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\ReqFailedLog
 *
 * @property int $id
 * @property string $url
 * @property string $params
 * @property string $ip
 * @property string $response
 * @property string $ua
 * @property string $created_at
 * @method static \Illuminate\Database\Eloquent\Builder|ReqFailedLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ReqFailedLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ReqFailedLog query()
 * @method static \Illuminate\Database\Eloquent\Builder|ReqFailedLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ReqFailedLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ReqFailedLog whereIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ReqFailedLog whereParams($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ReqFailedLog whereResponse($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ReqFailedLog whereUa($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ReqFailedLog whereUrl($value)
 * @mixin \Eloquent
 */
class ReqFailedLog extends Model
{
    protected $table = 'req_failed_log';
    public $timestamps = false;

    protected $guarded =[];
}