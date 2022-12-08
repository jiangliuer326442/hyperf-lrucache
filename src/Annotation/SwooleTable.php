<?php

namespace Mustafa\Lrucache\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

#[Attribute(Attribute::TARGET_CLASS)]
class SwooleTable extends AbstractAnnotation
{
    public int $swooleTableSize;

    public int $lruLimit;

    public int $hashKeyLength;
}
