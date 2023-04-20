<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ExchangeLog extends Model
{
    protected $table = 'exchange_log';
    public $timestamps = false;

    protected $guarded =[];

    public function getFromSymbolAttribute($val)
    {
        return strtoupper($val);
    }

    public function getToSymbolAttribute($val)
    {
        return strtoupper($val);
    }

    public function getCreatedAtAttribute($val)
    {
        return ( is_numeric($val) && $val > 0)  ? date("Y-m-d H:i:s", $val) : '-';
    }

    public function getStatusAttribute($val)
    {
        return $val === 1 ? trans('wallet.Status Success')  : trans('wallet.Status False');
    }



}
