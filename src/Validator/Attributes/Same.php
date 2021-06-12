<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Same extends ValidationRule
{
    public function __construct(
        public string $field,
        public string $scene = '',
        public string $errorMsg = '',
    )
    {
    }
}