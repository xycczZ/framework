<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Exists extends ValidationRule
{
    public function __construct(
        public string $table,
        public string $field = '', // default property name
        public array|\Closure $ignore,
        public string $scene = '',
        public string $errorMsg = '',
    )
    {
    }
}