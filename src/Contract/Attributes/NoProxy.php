<?php
declare(strict_types=1);

namespace Xycc\Winter\Contract\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class NoProxy
{
}