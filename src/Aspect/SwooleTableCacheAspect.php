<?php

declare(strict_types=1);

namespace Mustafa\Lrucache\Aspect;

use Hyperf\DbConnection\Collector\TableCollector;
use Mustafa\Lrucache\Annotation\SwooleTableCache;
use Mustafa\Lrucache\LRUCacheManager;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use PhpCsFixer\ConfigInterface;
use Psr\Container\ContainerInterface;

#[Aspect(annotations: [SwooleTableCache::class])]
class SwooleTableCacheAspect extends AbstractAspect
{

    public function __construct(protected TableCollector $collector, protected \Hyperf\Contract\ConfigInterface $config){
    }

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $annotation = AnnotationCollector::getClassMethodAnnotation($proceedingJoinPoint->className, $proceedingJoinPoint->methodName);

        $model = make($proceedingJoinPoint->className);
        $table = $model->getTable();

        $expire = $this->config->get("lrncache.redis.expire");
        if ($annotation[SwooleTableCache::class]->expire){
            $expire = $annotation[SwooleTableCache::class]->expire;
        }

        $cache = LRUCacheManager::instance($table);

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

        $ret = $proceedingJoinPoint->process();

        if ($ret) {
            $attributes = $ret->getAttributes();
            $cache->put(
                $table . ':',
                $proceedingJoinPoint->getArguments()[0],
                $attributes,
                $expire
            );
        }

        return $ret;
    }
}
