<?php

namespace Mustafa\Lrucache;

class LRUCacheManager
{
    private static array $lruCacheList = [];

    public static function register(string $entity, \Mustafa3264\Lrucache\Core\LRUCache $LRUCache): void
    {
        self::$lruCacheList[$entity] = $LRUCache;
    }

    public static function instance(string $entity): \Mustafa3264\Lrucache\Core\LRUCache
    {
        return self::$lruCacheList[$entity];
    }
}
