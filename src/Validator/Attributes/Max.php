<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Max extends Rule
{
    public function __construct(
        public int|float $max,
        public array $scenes = ['default'],
        public string $errorMsg = '',
    )
    {
    }
}