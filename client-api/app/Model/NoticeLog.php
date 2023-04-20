<?php

namespace App\Model;

class NoticeLog extends Model
{
    protected $table = 'notice_log';
    protected $guarded = [];

    public static function getList($user_id, $type, $chain_id, $page, $size)
    {
        $model = self::query()
            ->when($type, function ($query) use ($type) {
                $query->where('type', $type);
            })->when($chain_id, function ($query) use ($chain_id) {
                $query->where('chain_id', $chain_id);
            })
            ->where('user_id', $user_id);
        $count = $model->count();
        $offset = ($page - 1) * $size;
        $list = $model->offset($offset)->limit($size)->orderByRaw("status asc,created_at desc")->get()->each(function ($item) {
            $item['type'] = in_array($item['type'], [1, 3]) ? 1 : 2;
        })->toArray();
        return [
            'count' => $count,
            'list' => $list
        ];
    }
}