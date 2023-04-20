<?php

namespace App\Utils;

use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;

class Redis
{
    /**
     * 获取redis实例
     * @param string $pool
     * @return \Hyperf\Redis\RedisProxy
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public static function getInstance(string $pool = 'default')
    {
        return ApplicationContext::getContainer()->get(RedisFactory::class)->get($pool);
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public static function __callStatic($name, $arguments)
    {
        return self::getInstance()->$name(...$arguments);
    }
}