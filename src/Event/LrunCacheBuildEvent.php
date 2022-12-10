<?php

namespace Mustafa\Lrucache\Event;

class LrunCacheBuildEvent
{
    public string $table;

    public function __construct(string $table)
    {
        $this->table = $table;
    }
}