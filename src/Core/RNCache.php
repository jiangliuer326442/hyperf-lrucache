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

        $lua = "
                local key=KEYS[1];
                local hash_key=KEYS[2];
                local ret1 = redis.call('hget', 'hash:' .. key, hash_key);
                local ret2 = redis.call('zscore', 'zset:' .. key, hash_key);
                return {ret1, ret2};
            ";

        [$seralized_val, $expired_at] = $redis->eval($lua, [$_key, substr($key, -$this->hash_key_length, $this->hash_key_length)], 2);
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

            $lua = "
                local key=KEYS[1];
                local hash_key=KEYS[2];
                local hash_val=KEYS[3];
                local expire=KEYS[4];
                redis.call('hset', 'hash:' .. key, hash_key, hash_val);
                if redis.call('ttl','hash:' .. key)==-1 then
                  redis.call('expire', 'hash:' .. key, 172800)
                end
                redis.call('zadd', 'zset:' .. key, expire, hash_key);
                if redis.call('ttl','zset:' .. key)==-1 then
                  redis.call('expire', 'zset:' .. key, 172800)
                end
            ";

            $redis->eval($lua, [$_key, substr($key, -$this->hash_key_length, $this->hash_key_length), serialize($value), $expireAt], 4);
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

                $lua = "
                    local key=KEYS[1];
                    local hash_key=KEYS[2];
                    redis.call('hdel', 'hash:' .. key, hash_key);
                    redis.call('zrem', 'zset:' .. key, hash_key);
                ";

                $redis->eval($lua, [$_key, substr($key, -$this->hash_key_length, $this->hash_key_length)], 2);
            }
        }
    }
}
