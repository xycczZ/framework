<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Unique extends ValidationRule
{
    public function __construct(
        public string $table,
        public string $field = '', // default field name
        public $ignore = null,
        public string $scene = '',
        public string $errorMsg = '',
    )
    {
    }
}