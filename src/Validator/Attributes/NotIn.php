<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class NotIn extends ValidationRule
{
    public function __construct(
        public array $range,
        public string $scene = '',
        public string $errorMsg = '',
    )
    {
    }
}