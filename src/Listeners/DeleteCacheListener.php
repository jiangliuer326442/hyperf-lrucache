<?php

declare(strict_types=1);

namespace Mustafa\Lrucache\Listeners;

use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;
use Hyperf\Database\Model\Events\deleting;
use Hyperf\Database\Model\Events\Event;
use Hyperf\Redis\RedisFactory;
use Hyperf\Database\Model\Events\updating;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\ModelCache\CacheableInterface;
use Mustafa\Lrucache\Annotation\SwooleTable;
use Mustafa\Lrucache\LRUCacheManager;

class DeleteCacheListener implements ListenerInterface
{
    private string $pool;

    private string $prefix;

    public function __construct(protected ConfigInterface $config, protected ContainerInterface $container){
        $this->pool = $this->config->get('lrncache.redis.pool');
        $this->prefix = $this->config->get('lrncache.redis.prefix');
    }

    public function listen(): array
    {
        return [
            deleting::class,
            updating::class,
        ];
    }

    public function process(object $event) : void
    {
        if ($event instanceof Event) {
            $model = $event->getModel();
            $ret = AnnotationCollector::getClassAnnotation(get_class($model), SwooleTable::class);
            if ($ret) {
                $table = $model->getTable();
                $primaryKey = $model->getKeyName();
                $cache = LRUCacheManager::instance($table);
                $cache->del($table . ':', (string)$model->$primaryKey);
                $redis = $this->container->get(RedisFactory::class)->get($this->pool);
                $redis->publish($this->prefix . 'channel:swooletable:update', serialize([
                    "table" => $table,
                    "key" => (string)$model->$primaryKey,
                ]));
            }
        }
    }
}
