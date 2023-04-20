<?php
namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class Markets extends Model
{
    protected $table = 'markets';
    public $timestamps = false;

    protected $guarded =[];

}
