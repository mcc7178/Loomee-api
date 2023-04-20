<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $id 
 * @property string $block_number 
 * @property string $hash 
 * @property string $from 
 * @property string $to 
 * @property string $result 
 * @property \Carbon\Carbon $created_at 
 * @property \Carbon\Carbon $updated_at 
 * @property int $status 
 * @property string $symbol 
 * @property string $quantity 
 * @property string $type 
 * @property int $user_id 
 * @property string $input 
 * @property int $checkHash 
 * @property int $checkNum 
 */
class BlockLog extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'block_log';
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
    protected $casts = ['id' => 'integer', 'date' => 'integer', 'status' => 'integer'];
}