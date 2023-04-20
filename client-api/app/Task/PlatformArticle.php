<?php
namespace App\Task;

use App\Utils\Redis;

class PlatformArticle
{
	protected $description = '平台文章爬虫数据';

    protected $key_list = [1 => 'jinse'];
    public function handle(){
        $p_key = $this->key_list[1];
        $redis = Redis::getInstance();
        $data = $redis->hgetall($p_key);
        try {
            foreach ($data as $key => $item){
                $value = json_decode($item,true);
                if (\App\Model\PlatformArticle::query()->where('title',$value['title'])->get()){
                    $redis->hDel($p_key,$key);
                    continue;
                }
                $insert_data = [
                    'title' => $value['title'],
                    'summary' => $value['summary'],
                    'thumbnails_pics' => $value['thumbnails_pics'][0] ?? '',
                    'published_at' => $value['published_at'],
                    'topic_url' => $value['topic_url'] ?? '',
                    'detail' => $value['detail'],
                    'platform' => $p_key
                ];
                if (\App\Model\PlatformArticle::create($insert_data))
                    $redis->hDel($p_key,$key);
            }
        }catch (\Throwable $ex){
            var_dump($ex->getMessage());
        }
    }
}