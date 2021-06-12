<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Ip extends ValidationRule
{
    public function __construct(
        public bool $v6 = false,
        public string $scene = '',
        public string $errorMsg = '',
    )
    {
    }
}