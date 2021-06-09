<?php
declare(strict_types=1);

namespace Xycc\Winter\Contract\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_ALL)]
class Required
{
    public function __construct(public bool $required = true)
    {
    }
}