<?php

declare(strict_types=1);

namespace Mustafa\Lrucache\Core;

interface RNCacheInterface
{
    /**
     * 获取key对应的缓存信息.
     * @param string $key
     * @return array
     *               ret 缓存的数据
     *               ex 数据过期时间
     */
    public function get(string $key): array;

    /**
     * 保存key.
     * @param string $key
     * @param array $value
     * @param int $expire
     */
    public function set(string $key, array $value, int $expire): void;

    /**
     * 删除key.
     * @param string $key
     */
    public function del(string $key): void;
}
