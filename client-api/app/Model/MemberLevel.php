<?php
namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class MemberLevel extends Model
{
    protected $table = 'member_level';
    public $timestamps = false;

    public function user()
    {
        return $this->hasOne(\App\Model\User::class, 'id','child_uid');
    }
}
