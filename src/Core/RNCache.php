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
        $redis = $this->container->get(RedisFactory::class)->get($this->pool);
        list($_key, $_hash) = $this->explodeHash($key, $index, date('Ymd'));

        $seralized_val = $redis->hGet($_key . ":hash", $_hash);
        $expired_at = $redis->zScore($_key . ":zset", $_hash);

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
        for ($i = $current; $i < $expireAt + 86400; $i = $i + 86400) {
            list($_key, $_hash) = $this->explodeHash($key, $index, date('Ymd', $i));

            $redis->hSet($_key . ':hash', $_hash, serialize($value));
            $hash_ttl = $redis->ttl($_key . ':hash');
            if ($hash_ttl == -1){
                $redis->expire($_key . ':hash', 172800);
            }
            $redis->zAdd($_key . ':zset', $expireAt, $_hash);
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
            for ($i = $current; $i < $expireAt + 86400; $i = $i + 86400) {
                list($_key, $_hash) = $this->explodeHash($key, $index, date('Ymd', $i));

                $redis->hDel($_key . ':hash', $_hash);
                $redis->zRem($_key . ':zset', $_hash);

            }
        }
    }

    public function getHashKeyLength(): int
    {
        return $this->hash_key_length;
    }

    public function explodeHash(string $key, string $index, string $ymd): array
    {
        if ($this->hash_key_length > 0) {
            $raw_key = $key.$index;
            $key_prefix = substr($raw_key, 0, strlen($raw_key) - $this->hash_key_length);
            if ($key_prefix){
                $key_prefix .= ':';
            }
            $_key = $this->prefix . $key_prefix . $ymd;
            $_hash = substr($raw_key, -$this->hash_key_length, $this->hash_key_length);
        }else{
            $_key = $this->prefix . $key . $ymd;
            $_hash = $index;
        }
        return [$_key, $_hash];
    }
}
