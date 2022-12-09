<?php

declare(strict_types=1);

namespace Mustafa\Lrucache\Listeners;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Coordinator\Constants;
use Hyperf\Coordinator\CoordinatorManager;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Metric\Event\MetricFactoryReady;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Utils\Coroutine;
use Mustafa\Lrucache\Annotation\SwooleTable;
use Mustafa\Lrucache\SwooleTableManage;
use Psr\Container\ContainerInterface;
use Swoole\Timer;

class MetricListener implements ListenerInterface
{

    public function __construct(protected ContainerInterface $container)
    {
    }

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
        $config = $this->container->get(ConfigInterface::class);
        $timerInterval = $config->get('metric.default_metric_interval', 5);
        $timerId = Timer::tick($timerInterval * 1000, function () use (
            $tables,
            $swooletable_length_gauge,
            $swooletable_lru_length_gauge,
            $swooletable_max_length_gauge,
            $swooletable_size_gauge
        ) {
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
        });

        Coroutine::create(function () use ($timerId) {
            CoordinatorManager::until(Constants::WORKER_EXIT)->yield();
            Timer::clear($timerId);
        });
    }

    private function getAnnotationTables(): array
    {
        return AnnotationCollector::getClassesByAnnotation(SwooleTable::class);
    }
}