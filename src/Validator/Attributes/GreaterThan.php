<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[Attribute]
#[ValidateAllData]
class GreaterThan extends Rule
{
    public function __construct(
        public string $field,
        public bool $eq = false,
        public array $scenes = ['default'],
        public string $errorMsg = '',
    )
    {
    }
}