<?php

declare(strict_types=1);

namespace Mustafa\Lrucache\Listeners;

use Hyperf\Database\Model\Events\deleting;
use Hyperf\Database\Model\Events\Event;
use Hyperf\Database\Model\Events\updating;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\ModelCache\CacheableInterface;
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
            echo "hahaha";
        }
    }
}