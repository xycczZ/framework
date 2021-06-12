<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Regex extends ValidationRule
{
    public function __construct(
        public string $regex,
        public bool $not = false,
        public string $scene = '',
        public string $errorMsg = '',
    )
    {
    }
}