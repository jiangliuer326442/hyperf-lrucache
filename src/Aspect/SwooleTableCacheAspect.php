<?php

declare(strict_types=1);

namespace Mustafa\Lrucache\Aspect;

use Mustafa\Lrucache\Annotation\SwooleTable;
use Mustafa\Lrucache\Annotation\SwooleTableCache;
use Mustafa\Lrucache\LRUCacheManager;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;

#[Aspect(annotations: [SwooleTableCache::class])]
class SwooleTableCacheAspect extends AbstractAspect
{
    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $annotation = AnnotationCollector::getClassMethodAnnotation($proceedingJoinPoint->className, $proceedingJoinPoint->methodName);

        $swooletableAnonotation = AnnotationCollector::getClassAnnotation($annotation[SwooleTableCache::class]->entity, SwooleTable::class);

        $cache = LRUCacheManager::instance($swooletableAnonotation->table);

        if ($annotation[SwooleTableCache::class]->action === 'get') {
            $ret = $cache->get($swooletableAnonotation->table . ':', $proceedingJoinPoint->getArguments()[0]);

            if ($ret) {
                return $ret;
            }
        }

        $ret = $proceedingJoinPoint->process();

        if (in_array($annotation[SwooleTableCache::class]->action, ['get', 'set'])) {
            if ($ret) {
                $cache->put(
                    $swooletableAnonotation->table . ':',
                    $proceedingJoinPoint->getArguments()[0],
                    $ret,
                    $annotation[SwooleTableCache::class]->expire
                );
            }
        } elseif (in_array($annotation[SwooleTableCache::class]->action, ['del'])) {
            if ($ret) {
                $cache->del(
                    $swooletableAnonotation->table . ':',
                    $proceedingJoinPoint->getArguments()[0]
                );
            }
        }

        return $ret;
    }
}
