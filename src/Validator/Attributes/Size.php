<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Size extends Rule
{
    public function __construct(
        public int $start,
        public int $end,
        public bool $startClose = true,
        public bool $endClose = true,
        public array $scenes = ['default'],
        public string $errorMsg = '',
    )
    {
    }
}