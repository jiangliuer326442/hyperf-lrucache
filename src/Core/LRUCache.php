<?php

declare(strict_types=1);

namespace Mustafa\Lrucache\Core;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Annotation\Inject;

class LRUCache
{

    private HashList $list;

    /**
     * LRUCache constructor.
     * @param string $table
     * @param int $limit
     */
    public function __construct(string $table, protected int $limit, protected RNCacheInterface $RNCache, protected ConfigInterface $config)
    {
        $this->list = make(HashList::class, [$table, $this->RNCache]);
    }

    /**
     * @param string $key
     * @param string $index
     * @return array
     */
    public function get(string $key, string $index): array
    {
        if (!$key) {
            return [];
        }
        $hash_key_length = $this->RNCache->getHashKeyLength();
        if ($hash_key_length > 0) {
            $index = str_pad($index, $hash_key_length, '0', STR_PAD_LEFT);
        }
        return $this->list->get($key, $index);
    }

    /**
     * @param string $key key基础
     * @param string $index key索引
     * @param array $value 数据内容
     * @param int $expire 多少秒后过期
     */
    public function put(string $key, string $index, array $value, int $expire = -1): void
    {
        $hash_key_length = $this->RNCache->getHashKeyLength();
        if ($hash_key_length > 0) {
            $index = str_pad($index, $hash_key_length, '0', STR_PAD_LEFT);
        }
        $size = $this->list->getSize();
        $isHas = $this->list->checkIndex($key . $index);
        if ($isHas || $size + 1 > $this->limit) {
            $this->list->removeNode($key, $index);
        }
        $this->list->addAsHead($key . $index, $value, $expire);

        $this->RNCache->set($key, $index, $value, $expire);
    }

    public function del(string $key, string $index): void
    {
        $hash_key_length = $this->RNCache->getHashKeyLength();
        if ($hash_key_length > 0) {
            $index = str_pad($index, $hash_key_length, '0', STR_PAD_LEFT);
        }
        $isHas = $this->list->checkIndex($key . $index);
        if ($isHas) {
            $this->list->removeNode($key, $index);
        }

        $this->RNCache->del($key, $index);
    }
}
