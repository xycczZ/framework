<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;
use Closure;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Exists extends Rule
{
    public function __construct(
        public string $table,
        public string $field = '', // default property name
        public array|Closure|null $ignore = null,
        public array $scenes = ['default'],
        public string $errorMsg = '',
    )
    {
    }
}