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
                $stats = $table->stats();
                echo $table_name;
                echo PHP_EOL;
                echo $record_num;
                echo PHP_EOL;
                echo $max_record_num;
                echo PHP_EOL;
                echo $record_max_num;
                echo PHP_EOL;
                echo $memory_size;
                echo PHP_EOL;
                print_r($stats);
                echo "==================" . PHP_EOL;
            }
            sleep(10);
        }
    }

    private function getAnnotationTables(): array
    {
        return AnnotationCollector::getClassesByAnnotation(SwooleTable::class);
    }
}