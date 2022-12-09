<?php

declare(strict_types=1);

namespace Mustafa\Lrucache;

use Mustafa\Lrucache;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                Lrucache\Core\RNCacheInterface::class => Lrucache\Core\RNCache::class,
            ],
            'listeners' => [
                Lrucache\Listeners\BootProcessListener::class,
                Lrucache\Listeners\DeleteCacheListener::class,
                Lrucache\Listeners\MetricListener::class
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for lru cache.',
                    'source' => __DIR__ . '/../publish/lrncache.php',
                    'destination' => BASE_PATH . '/config/autoload/lrncache.php',
                ],
            ],
        ];
    }
}