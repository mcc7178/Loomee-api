<?php

declare(strict_types=1);

namespace App\Model\Loomee;

use App\Model\Model;
use App\Foundation\Traits\DataRedis;
use App\Foundation\Facades\Log;

/**
 * Class Member
 * @package App\Model\Loomee
 * @Author hy
 * @Date: 2021/4/12
 */
class Member extends Model
{
    use DataRedis;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'member';

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



    public function getList()
    {
        $data = $this->getCache();
        if(empty($data))
        {
            $data = $this->orderBy('id','desc')->paginate(15);
            $this->synCache($data);
        }

        return $data;
    }


}