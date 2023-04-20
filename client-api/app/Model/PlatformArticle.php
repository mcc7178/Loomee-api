<?php
declare(strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class PlatformArticle extends Model
{
    protected $table = 'platform_article';

    public function getPublishedAtAttribute($value)
    {
        return date('Y-m-d',$value);
    }
}
