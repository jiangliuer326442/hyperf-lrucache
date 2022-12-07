<?php

declare(strict_types=1);

namespace Mustafa\Lrucache\Aspect;

use Hyperf\DbConnection\Collector\TableCollector;
use Mustafa\Lrucache\Annotation\SwooleTable;
use Mustafa\Lrucache\Annotation\SwooleTableCache;
use Mustafa\Lrucache\LRUCacheManager;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Psr\Container\ContainerInterface;

#[Aspect(annotations: [SwooleTableCache::class])]
class SwooleTableCacheAspect extends AbstractAspect
{
    protected TableCollector $collector;

    public function __construct(\Hyperf\Contract\ContainerInterface $container){
        $this->collector = $container->get(TableCollector::class);
    }

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $annotation = AnnotationCollector::getClassMethodAnnotation($proceedingJoinPoint->className, $proceedingJoinPoint->methodName);

        $model = make($annotation[SwooleTableCache::class]->entity);
        $table = $model->getTable();

        $swooletableAnonotation = AnnotationCollector::getClassAnnotation($annotation[SwooleTableCache::class]->entity, SwooleTable::class);

        $cache = LRUCacheManager::instance($table);

        if ($annotation[SwooleTableCache::class]->action === 'get') {
            $data = $cache->get($table . ':', $proceedingJoinPoint->getArguments()[0]);
            if ($data) {
                $connection = $model->getConnectionName();
                $defaultData = $this->collector->getDefaultValue(
                    $connection,
                    $table
                );
                $attributes = array_replace($defaultData, $data);

                return $model->newFromBuilder($attributes);
            }
        }

        $ret = $proceedingJoinPoint->process();

        if (in_array($annotation[SwooleTableCache::class]->action, ['get', 'set'])) {
            if ($ret) {
                $attributes = $ret->getAttributes();
                $cache->put(
                    $table . ':',
                    $proceedingJoinPoint->getArguments()[0],
                    $attributes,
                    $annotation[SwooleTableCache::class]->expire
                );
            }
        } elseif (in_array($annotation[SwooleTableCache::class]->action, ['del'])) {
            if ($ret) {
                $cache->del(
                    $table . ':',
                    $proceedingJoinPoint->getArguments()[0]
                );
            }
        }

        return $ret;
    }
}
