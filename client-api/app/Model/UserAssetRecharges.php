<?php
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
//use Laravel\Lumen\Routing\UrlGenerator;

class UserAssetRecharges extends Model
{
    protected $table = 'user_asset_recharges';

    public $timestamps = false;

    protected $guarded =[];

    protected $fillable = [
        'userid',
        'coin',
        'quantity',
        'freeze'
    ];

}
