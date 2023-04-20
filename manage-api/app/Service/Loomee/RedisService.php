<?php

namespace App\Service\Loomee;

use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;


class RedisService
{
    private static $_instance = null;
    private static $handler = null;


    private function __construct($select = 0, $pool = 'default')
    {
        if (!extension_loaded('redis')) {
            throw new \BadFunctionCallException('not support: redis');      //判断是否有扩展
        }

        $container     = ApplicationContext::getContainer();
        self::$handler = $container->get(RedisFactory::class)->get($pool);

        if (0 != $select) {
            self::$handler->select($select);
        }

    }


    public static function getInstance($pool = 'default', $select = 0)
    {
        //TODO  后续优化
        self::$_instance[$pool][$select] = new self($select, $pool);
        return self::$_instance[$pool][$select];
    }

    /*
     * 禁止外部克隆
     */
    final public function __clone()
    {
        trigger_error('Clone is not allow!', E_USER_ERROR);
    }


    public function __call($method, $args)
    {
        return call_user_func_array([self::$handler, $method], $args);
    }

    /**
     * 静态调用
     *
     * @param $method
     * @param $args
     *
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        return call_user_func_array([self::$handler, $method], $args);
    }

}