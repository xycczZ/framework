<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Max extends ValidationRule
{
    public function __construct(
        public int|float $max,
        public string $scene = '',
        public string $errorMsg = '',
    )
    {
    }
}