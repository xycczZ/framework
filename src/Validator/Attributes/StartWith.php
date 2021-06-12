<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class StartWith extends ValidationRule
{
    public function __construct(
        public string $start,
        public string $scene = '',
        public string $errorMsg = '',
    )
    {
    }
}