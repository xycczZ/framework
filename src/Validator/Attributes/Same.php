<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
#[ValidateAllData]
class Same extends Rule
{
    public function __construct(
        public string $field,
        public array $scenes = ['default'],
        public string $errorMsg = '',
    )
    {
    }
}