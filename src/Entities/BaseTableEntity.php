<?php

namespace Mustafa\Lrucache\Entities;

use Mustafa\Lrucache\Annotation\SwooleTableItem;

abstract class BaseTableEntity
{
    #[SwooleTableItem(name: '_pre', type: \Swoole\Table::TYPE_STRING, length: 64)]
    public int $prev;

    #[SwooleTableItem(name: '_next', type: \Swoole\Table::TYPE_STRING, length: 64)]
    public int $next;

    #[SwooleTableItem(name: '_expire', type: \Swoole\Table::TYPE_INT, length: 64)]
    public int $expire;
}
