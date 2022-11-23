<?php

declare(strict_types=1);

namespace Mustafa\Lrucache\Listeners;

use Mustafa\Lrucache\Annotation\SwooleTable;
use Mustafa\Lrucache\Annotation\SwooleTableItem;
use Mustafa\Lrucache\Core\LRUCache;
use Mustafa\Lrucache\LRUCacheManager;
use Mustafa\Lrucache\SwooleTableManage;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Framework\Event\BeforeMainServerStart;

class BootProcessListener implements \Hyperf\Event\Contract\ListenerInterface
{
    /**
     * {@inheritDoc}
     */
    public function listen(): array
    {
        return [
            BeforeMainServerStart::class,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function process(object $event): void
    {
        $tables = $this->getAnnotationTables();
        foreach ($tables as $table_class => $swooletableobj) {
            $table_name = $swooletableobj->table;
            $swooleTableSize = $swooletableobj->swooleTableSize;

            $swooletable = new \Swoole\Table($swooleTableSize);

            $rows = $this->getAnnotationTableRowsByClass($table_class);

            foreach ($rows as $row) {
                $swooletable->column($row['annotation']->name, $row['annotation']->type, $row['annotation']->length);
            }
            $swooletable->create();
            SwooleTableManage::register($table_name, $swooletable);

            $lrucache = make(LRUCache::class, [$table_name, $swooletableobj->lruLimit]);
            LRUCacheManager::register($table_name, $lrucache);
        }
    }

    private function getAnnotationTables(): array
    {
        return AnnotationCollector::getClassesByAnnotation(SwooleTable::class);
    }

    private function getAnnotationTableRowsByClass(string $clazz): array
    {
        return array_filter(AnnotationCollector::getPropertiesByAnnotation(SwooleTableItem::class), function ($item) use ($clazz) {
            return $item['class'] === $clazz;
        });
    }
}
