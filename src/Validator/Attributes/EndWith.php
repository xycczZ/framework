<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class EndWith extends ValidationRule
{
    public function __construct(
        public string $end,
        public string $scene = '',
        public string $errorMsg = '',
    )
    {
    }
}