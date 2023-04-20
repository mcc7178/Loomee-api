<?php
namespace App\Foundation\Traits;

use App\Constants\RedisCode;
use App\Service\Loomee\RedisService;
use App\Foundation\Facades\Log;
use Exception;
use Hyperf\Database\Model\Events\Saved;
use Hyperf\Database\Model\Events\Deleted;
use Hyperf\Database\Model\Events\Updated;

trait DataRedis
{
    protected $data_key;
    protected $cache_host = 'default';

    /**
     * 获取redis
     */
    public function cRedis()
    {
        try{
            return RedisService::getInstance($this->cache_host);
        }
        catch(Exception $e){
            return false;
        }
    }

    /**
     * 设置当前key
     *
     * @param string $str
     * @return void
     */
    public function setModelCacheKey(string $action = '',string $str = '')
    {
        $key = RedisCode::HOME.':'.$this->table;
        if($action != '')   $key .= ':'.$action;
        if($action != '')   $key .= ':'.md5($str);
        $this->data_key = $key;
        // Log::debugLog()->error($key);
    }

    /**
     * 获取缓存数据
     */
    public function getCache()
    {
        if($this->cRedis() === false)   return null;

        // $data = $this->cRedis()->get($this->data_key);
        // if(!empty($data))
        //     return json_decode($data,true);
        // else
            return [];
    }

    /**
     * 同步缓存
     */
    public function synCache($data,int $time = 0)
    {
        if($this->cRedis() === false)   return null;

        // $str = '';
        // if(!is_string($data)) $str = json_encode($data);
        // if($str != '')
        // {
        //     if($time == 0)
        //         $this->cRedis()->set($this->data_key,json_encode($data));
        //     else
        //         $this->cRedis()->set($this->data_key,json_encode($data),$time);
        // } 
    }

    /**
     * 获取所有缓存key
     */
    public function getAllCacheKey()
    {
        if($this->cRedis() === false)   return null;

        $list = $this->cRedis()->keys($this->data_key."*");

        return $list;
    }

    /**
     * 删除缓存
     */
    public function delCache()
    {
        if($this->cRedis() === false)   return null;
        
        $this->setModelCacheKey();
        $list = $this->getAllCacheKey();
        foreach($list as $k=>$v)
        {
            // RedisService::getInstance($this->cache_host)->redis->del((string)$v);
            $this->cRedis()->set($v,'',1);
        }
    }


    // 创建和更新后
    public function Saved(Saved $event)
    {
        $this->delCache();
    }

    // 删除后
    public function Deleted(Deleted $event)
    {
        $this->delCache();
    }

    // 更新后
    public function Updated(Updated $event)
    {
        $this->delCache();
    }


    /**
     * 缓存当前表数据
     *
     * @param array $field
     * @return void
     */
    public function cacheDataList(array $field = [])
    {
        $this->setModelCacheKey('all',json_encode($field));
        $data = $this->getCache();
        if(empty($data))
        {
            if(empty($field))
                $data = $this->get()->toArray();

            if(count($field) == 1)
                $data = $this->pluck($field[0]);

            if(count($field) == 2)
                $data = $this->pluck($field[0],$field[1]);

            $this->synCache($data);
        }
        
        return $data;
    }


}
