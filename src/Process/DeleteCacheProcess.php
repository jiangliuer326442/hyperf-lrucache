<?php
declare(strict_types=1);

namespace Mustafa\Lrucache\Process;

use Hyperf\Process\AbstractProcess;
use Hyperf\Redis\RedisFactory;
use Psr\Container\ContainerInterface;
use Hyperf\Contract\ConfigInterface;

class DeleteCacheProcess extends AbstractProcess
{
    public string $name = 'swooletable_process';

    private string $pool;

    private string $prefix;

    public function __construct(protected ContainerInterface $container, protected ConfigInterface $config)
    {
        parent::__construct($this->container);
        $this->pool = $this->config->get('lrncache.redis.pool');
        $this->prefix = $this->config->get('lrncache.redis.prefix');
    }

    public function handle(): void
    {
        $redis = $this->container->get(RedisFactory::class)->get($this->pool);
        co(function () use ($redis){
            begin:
            try {
                $redis->subscribe([$this->prefix . 'channel:swooletable:update'], function ($redis, $chan, $msg) {
                    echo 'channel:'.$chan.',message:'.$msg."\n";
                });
            }catch (\Exception $e){
                echo $e->getMessage();
                $redis = $this->container->get(RedisFactory::class)->get($this->pool);
                goto begin;
            }
        });
    }
}