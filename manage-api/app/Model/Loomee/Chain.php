<?php

declare(strict_types=1);

namespace App\Model\Loomee;

use App\Foundation\Utils\Cron;
use App\Foundation\Traits\DataRedis;
use App\Model\Model;

/**
 * Class Coin
 * @package App\Model\Loomee
 * @Author hy
 * @Date: 2021/4/12
 */
class Chain extends Model
{
    use DataRedis;
    
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'chain';

    /**
     * The connection name for the model.
     *  
     * @var string
     */
    protected $connection = 'default';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [];

    public $timestamps = false;

}