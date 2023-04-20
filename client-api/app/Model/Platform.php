<?php

declare (strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

/**
 * @property int $id
 * @property string $brand_url
 * @property string $help_url
 * @property string $clause_url
 * @property string $website
 * @property string $twitter
 * @property string $weibo
 * @property string $instagram
 * @property string $discord
 * @property string $telegrm
 * @property string $medium
 */
class Platform extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'platform';
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
    protected $casts = ['id' => 'integer'];

    public static function info()
    {
        return self::first()->toArray();
    }
}