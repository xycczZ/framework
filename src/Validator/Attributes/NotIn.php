<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class NotIn extends Rule
{
    public function __construct(
        public array $range,
        public array $scenes = ['default'],
        public string $errorMsg = '',
    )
    {
    }
}