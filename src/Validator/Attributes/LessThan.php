<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[Attribute]
class LessThan extends Rule
{
    public function __construct(
        public $value,
        public bool $eq = false,
        public array $scenes = ['default'],
        public string $errorMsg = '',
    )
    {
    }
}