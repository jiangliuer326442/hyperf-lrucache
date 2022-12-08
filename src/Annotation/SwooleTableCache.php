<?php

namespace Mustafa\Lrucache\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

#[Attribute(Attribute::TARGET_METHOD)]
class SwooleTableCache extends AbstractAnnotation
{

    public int $expire;
}
