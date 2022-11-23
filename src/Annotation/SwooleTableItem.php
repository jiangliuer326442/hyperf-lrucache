<?php

declare(strict_types=1);

namespace Mustafa\Lrucache\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

#[Attribute(Attribute::TARGET_PROPERTY)]
class SwooleTableItem extends AbstractAnnotation
{
    public string $name;

    public int $type;

    public int $length;
}
