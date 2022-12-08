<?php

declare(strict_types=1);

namespace Mustafa\Lrucache\Core;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Redis\RedisFactory;
use Psr\Container\ContainerInterface;

class RNCache implements RNCacheInterface
{
    private string $pool;

    private string $prefix;

    private int $hash_key_length;

    public function __construct(int $hash_key_length, protected ConfigInterface $config, protected ContainerInterface $container)
    {
        $this->hash_key_length = $hash_key_length;
        $this->pool = $this->config->get('lrncache.redis.pool');
        $this->prefix = $this->config->get('lrncache.redis.prefix');
    }

    public function get(string $key, string $index): array
    {
        $raw_key = $key . $index;
        $redis = $this->container->get(RedisFactory::class)->get($this->pool);
        $_key = $this->prefix . substr($raw_key, 0, strlen($raw_key) - $this->hash_key_length) . ':' . date('Ymd');

        $seralized_val = $redis->hGet($_key . ":hash", substr($raw_key, -$this->hash_key_length, $this->hash_key_length));
        $expired_at = $redis->zScore($_key . ":zset", substr($raw_key, -$this->hash_key_length, $this->hash_key_length));

        if ($seralized_val && $expired_at) {
            $val = unserialize($seralized_val);
            return [$val, $expired_at];
        }
        return [];
    }

    public function set(string $key, string $index, array $value, int $expire): void
    {
        $raw_key = $key . $index;
        $current = time();
        $expireAt = $current + $expire;
        $redis = $this->container->get(RedisFactory::class)->get($this->pool);
        for ($i = $current; $i < $expireAt; $i = $i + 86400) {
            $_key = $this->prefix . substr($raw_key, 0, strlen($raw_key) - $this->hash_key_length) . ':' . date('Ymd', $i);

            $redis->hSet($_key . ':hash', substr($raw_key, -$this->hash_key_length, $this->hash_key_length), serialize($value));
            $hash_ttl = $redis->ttl($_key . ':hash');
            if ($hash_ttl == -1){
                $redis->expire($_key . ':hash', 172800);
            }
            $redis->zAdd($_key . ':zset', $expireAt, substr($raw_key, -$this->hash_key_length, $this->hash_key_length));
            $zset_ttl = $redis->ttl($_key . ':zset');
            if ($zset_ttl == -1){
                $redis->expire($_key . ':zset', 172800);
            }
        }
    }

    public function del(string $key, string $index): void
    {
        $raw_key = $key . $index;
        $ret = $this->get($key, $index);
        if ($ret) {
            [$val, $expireAt] = $ret;
            $redis = $this->container->get(RedisFactory::class)->get($this->pool);
            $current = time();
            for ($i = $current; $i < $expireAt; $i = $i + 86400) {
                $_key = $this->prefix . substr($raw_key, 0, strlen($raw_key) - $this->hash_key_length) . ':' . date('Ymd', $i);

                $redis->hDel($_key . ':hash', substr($raw_key, -$this->hash_key_length, $this->hash_key_length));
                $redis->zRem($_key . ':zset', substr($raw_key, -$this->hash_key_length, $this->hash_key_length));

            }
        }
    }
}
