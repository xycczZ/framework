<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Size extends Rule
{
    public function __construct(
        public int|float $size,
        public array $scenes = ['default'],
        public string $errorMsg = '',
    )
    {
    }
}