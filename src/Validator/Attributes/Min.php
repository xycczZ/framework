<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Min extends ValidationRule
{
    public function __construct(
        public int|float $min,
        public string $scene = '',
        public string $errorMsg = '',
    )
    {
    }
}