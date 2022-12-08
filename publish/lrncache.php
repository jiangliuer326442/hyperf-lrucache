<?php

declare(strict_types=1);

return [
    'redis' => [
        'prefix' => 'swooletable:cache:', // redis key前缀
        'pool' => 'default', // 使用哪个redis连接池做缓存
        'hash_key_length' => 3, // 使用hash做缓存key的长度
        'expire' => 86400, //缓存默认过期时间
    ],
];
