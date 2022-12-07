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

    public function __construct(protected ConfigInterface $config, protected ContainerInterface $container)
    {
        $this->pool = $this->config->get('lrncache.redis.pool');
        $this->hash_key_length = $this->config->get('lrncache.redis.hash_key_length');
        $this->prefix = $this->config->get('lrncache.redis.prefix');
    }

    public function get(string $key): array
    {
        $redis = $this->container->get(RedisFactory::class)->get($this->pool);
        $_key = $this->prefix . substr($key, 0, strlen($key) - $this->hash_key_length) . ':' . date('Ymd');

        $seralized_val = $redis->hGet($_key . ":hash", substr($key, -$this->hash_key_length, $this->hash_key_length));
        $expired_at = $redis->zScore($_key . ":hash", substr($key, -$this->hash_key_length, $this->hash_key_length));

        if ($seralized_val && $expired_at) {
            $val = unserialize($seralized_val);
            return [$val, $expired_at];
        }
        return [];
    }

    public function set(string $key, array $value, int $expire): void
    {
        $current = time();
        $expireAt = $current + $expire;
        $redis = $this->container->get(RedisFactory::class)->get($this->pool);
        for ($i = $current; $i < $expireAt; $i = $i + 86400) {
            $_key = $this->prefix . substr($key, 0, strlen($key) - $this->hash_key_length) . ':' . date('Ymd', $i);

            $redis->hSet($_key . ':hash', substr($key, -$this->hash_key_length, $this->hash_key_length), serialize($value));
            $hash_ttl = $redis->ttl($_key . ':hash');
            if ($hash_ttl == -1){
                $redis->expire($_key . ':hash', 172800);
            }
            $redis->zAdd($_key . ':zset', $expireAt, substr($key, -$this->hash_key_length, $this->hash_key_length));
            $zset_ttl = $redis->ttl($_key . ':zset');
            if ($zset_ttl == -1){
                $redis->expire($_key . ':zset', 172800);
            }
        }
    }

    public function del(string $key): void
    {
        $ret = $this->get($key);
        if ($ret) {
            [$val, $expireAt] = $ret;
            $redis = $this->container->get(RedisFactory::class)->get($this->pool);
            $current = time();
            for ($i = $current; $i < $expireAt; $i = $i + 86400) {
                $_key = $this->prefix . substr($key, 0, strlen($key) - $this->hash_key_length) . ':' . date('Ymd', $i);

                $redis->hDel($_key . ':hash', substr($key, -$this->hash_key_length, $this->hash_key_length));
                $redis->zRem($_key . ':zset', substr($key, -$this->hash_key_length, $this->hash_key_length));

            }
        }
    }
}
