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
            $instance = make($table_class);
            $table_name = $instance->getTable();
            $swooleTableSize = $swooletableobj->swooleTableSize;

            $swooletable = new \Swoole\Table($swooleTableSize);

            $rows = $this->getAnnotationTableRowsByClass($table_class);

            foreach ($rows as $row) {
                $swooletable->column($row[0], $row[1], $row[2]);
            }
            $swooletable->create();
            SwooleTableManage::register($table_name, $swooletable);

            $lrucache = make(LRUCache::class, [$table_name, $swooletableobj->lruLimit, $swooletableobj->hash_key_length]);
            LRUCacheManager::register($table_name, $lrucache);
        }
    }

    private function getAnnotationTables(): array
    {
        return AnnotationCollector::getClassesByAnnotation(SwooleTable::class);
    }

    private function getAnnotationTableRowsByClass(string $clazz): array
    {
        $reflection = new \ReflectionClass($clazz);
        $doc = $reflection->getDocComment();
        $lines = explode("\n", $doc);
        $list = [];
        foreach ($lines as $line){
            if (strstr($line, '@property')){
                $cells = explode(" ", $line);
                if ($cells[3] == 'int'){
                    $type = \Swoole\Table::TYPE_INT;
                    $length = 11;
                }else{
                    $type = \Swoole\Table::TYPE_STRING;
                    $length = 255;
                }
                $name = substr($cells[4], 1);
                $list[] = [$name, $type, $length];
            }
        }
        $delete_at = defined('static::DELETED_AT') ? static::DELETED_AT : 'deleted_at';
        $list[] = [$delete_at, \Swoole\Table::TYPE_STRING, 64];
        $list[] = ["_pre", \Swoole\Table::TYPE_STRING, 64];
        $list[] = ["_next", \Swoole\Table::TYPE_STRING, 64];
        $list[] = ["_expire", \Swoole\Table::TYPE_INT, 64];
        return $list;
    }
}
