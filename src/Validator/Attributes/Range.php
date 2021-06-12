<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Range extends Rule
{
    public function __construct(
        public int|float $start,
        public int|float $end,
        public bool $startClose = true,
        public bool $endClose = true,
        public array $scenes = ['default'],
        public string $errorMsg = '',
    )
    {
    }
}