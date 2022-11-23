<?php

namespace Mustafa\Lrucache;

use Mustafa\Lrucache\Core\LRUCache;

class LRUCacheManager
{
    private static array $lruCacheList = [];

    public static function register(string $entity, LRUCache $LRUCache): void
    {
        self::$lruCacheList[$entity] = $LRUCache;
    }

    public static function instance(string $entity): LRUCache
    {
        return self::$lruCacheList[$entity];
    }
}
