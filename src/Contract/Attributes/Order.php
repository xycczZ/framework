<?php
declare(strict_types=1);

namespace Xycc\Winter\Contract\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_ALL)]
class Order
{
    public const DEFAULT = 10;

    public function __construct(public int $value = self::DEFAULT)
    {
    }
}