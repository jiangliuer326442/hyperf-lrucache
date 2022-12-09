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
                if (count(SwooleTableManage::instance($table_name))) {
                    echo $table_name;
                    echo count(SwooleTableManage::instance($table_name));
                }
            }
            sleep(10);
        }
    }

    private function getAnnotationTables(): array
    {
        return AnnotationCollector::getClassesByAnnotation(SwooleTable::class);
    }
}