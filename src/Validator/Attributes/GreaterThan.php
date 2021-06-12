<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[Attribute]
class GreaterThan extends ValidationRule
{
    public function __construct(
        public $value,
        public bool $eq = false,
        public string $scene = '',
        public string $errorMsg = '',
    )
    {
    }
}