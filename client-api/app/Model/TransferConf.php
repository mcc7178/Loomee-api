<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Laravel\Lumen\Routing\UrlGenerator;

class TransferConf extends Model
{
    protected $table = 'transfer_conf';

    public $timestamps = false;

    protected $guarded =[];
}
