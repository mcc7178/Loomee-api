<?php
namespace App\Utils;


use Hyperf\Utils\ApplicationContext;
use Psr\SimpleCache\CacheInterface;

class Cache
{
        public static function getInstance()
        {
                return ApplicationContext::getContainer()->get(CacheInterface::class);
        }

        public static function __callStatic($name, $arguments)
        {
                return self::getInstance()->$name(...$arguments);
        }
}