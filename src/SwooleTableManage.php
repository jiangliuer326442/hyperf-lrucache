<?php

declare(strict_types=1);

namespace Mustafa\Lrucache;

class SwooleTableManage
{
    private static array $swooleTableList = [];

    public static function register(string $entity, \Swoole\Table $table): void
    {
        self::$swooleTableList[$entity] = $table;
    }

    public static function instance(string $entity): \Swoole\Table
    {
        return self::$swooleTableList[$entity];
    }
}
