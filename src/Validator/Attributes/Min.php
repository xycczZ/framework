<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Min extends Rule
{
    public function __construct(
        public int|float $min,
        public array $scenes = ['default'],
        public string $errorMsg = '',
    )
    {
    }
}