<?php

declare(strict_types=1);

namespace App\Model\Collection;

use App\Model\Model;
use Psr\Container\ContainerInterface;

class CollectionCategory extends Model
{

    protected $container;
    protected $table = 'collection_category';
    protected $connection = 'default';

    protected $fillable = [];
    protected $casts = [];

    /**
     * Notes:合集分类
     * User: Deycecep
     * DateTime: 2022/4/18 15:35
     * @return mixed
     */
    static function getList()
    {
        return static::query()->where('status', 1)->get();
    }

}