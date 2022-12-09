<?php

declare(strict_types=1);

namespace Mustafa\Lrucache\Listeners;

use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Metric\Event\MetricFactoryReady;
use Hyperf\Di\Annotation\AnnotationCollector;
use Mustafa\Lrucache\Annotation\SwooleTable;
use Mustafa\Lrucache\SwooleTableManage;

class MetricListener implements ListenerInterface
{

    public function listen(): array
    {
        return [
            MetricFactoryReady::class,
        ];
    }

    public function process(object $event): void
    {
        $swooletable_length_gauge = $event
            ->factory
            ->makeGauge('swoole_table_length', ['table']);
        $swooletable_lru_length_gauge = $event
            ->factory
            ->makeGauge('swoole_table_lru_length', ['table']);
        $swooletable_max_length_gauge = $event
            ->factory
            ->makeGauge('swoole_table_max_length', ['table']);
        $swooletable_size_gauge = $event
            ->factory
            ->makeGauge('swoole_table_size', ['table']);
        $tables = $this->getAnnotationTables();
        while (true) {
            foreach ($tables as $table_class => $swooletableobj) {
                $instance = make($table_class);
                $table_name = $instance->getTable();
                $table = SwooleTableManage::instance($table_name);
                $record_num = $table->count();
                $max_record_num = $swooletableobj->lruLimit;
                $record_max_num = $table->size;
                $memory_size = $table->memorySize;
                $swooletable_length_gauge->with($table_name)->set($record_num);
                $swooletable_lru_length_gauge->with($table_name)->set($max_record_num);
                $swooletable_max_length_gauge->with($table_name)->set($record_max_num);
                $swooletable_size_gauge->with($table_name)->set($memory_size);
            }
            sleep(10);
        }
    }

    private function getAnnotationTables(): array
    {
        return AnnotationCollector::getClassesByAnnotation(SwooleTable::class);
    }
}