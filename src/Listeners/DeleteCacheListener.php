<?php

declare(strict_types=1);

namespace Mustafa\Lrucache\Listeners;

use Hyperf\Database\Model\Events\deleting;
use Hyperf\Database\Model\Events\Event;
use Hyperf\Database\Model\Events\updating;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\ModelCache\CacheableInterface;
use Mustafa\Lrucache\Annotation\SwooleTable;
use Mustafa\Lrucache\LRUCacheManager;

class DeleteCacheListener implements ListenerInterface
{
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
            }
        }
    }
}
