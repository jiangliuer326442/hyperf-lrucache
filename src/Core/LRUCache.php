<?php

declare(strict_types=1);

namespace Mustafa\Lrucache\Core;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Annotation\Inject;

class LRUCache
{
    #[Inject]
    private ConfigInterface $config;

    #[Inject]
    private RNCacheInterface $RNCache;

    private int $limit;

    private HashList $list;

    /**
     * LRUCache constructor.
     * @param string $table
     * @param int $limit
     */
    public function __construct(string $table, int $limit)
    {
        $this->limit = $limit;
        $this->list = new HashList($table);
    }

    /**
     * @param string $key
     * @return array
     */
    public function get(string $key, int $index): array
    {
        if (!$key) {
            return [];
        }
        $index = str_pad((string)$index, 3, '0', STR_PAD_LEFT);
        return $this->list->get($key . $index);
    }

    /**
     * @param string $key key基础
     * @param int $index key索引
     * @param array $value 数据内容
     * @param int $expire 多少秒后过期
     */
    public function put(string $key, int $index, array $value, int $expire = -1): void
    {
        $hash_key_length = $this->config->get('lrncache.redis.hash_key_length');
        $index = str_pad((string)$index, $hash_key_length, '0', STR_PAD_LEFT);
        $size = $this->list->getSize();
        $isHas = $this->list->checkIndex($key . $index);
        if ($isHas || $size + 1 > $this->limit) {
            $this->list->removeNode($key . $index);
        }
        $this->list->addAsHead($key . $index, $value, $expire);

        $this->RNCache->set($key . $index, $value, $expire);
    }

    public function del(string $key, int $index): void
    {
        $hash_key_length = $this->config->get('lrncache.redis.hash_key_length');
        $index = str_pad((string)$index, $hash_key_length, '0', STR_PAD_LEFT);
        $isHas = $this->list->checkIndex($key . $index);
        if ($isHas) {
            $this->list->removeNode($key . $index);
        }

        $this->RNCache->del($key . $index);
    }
}
