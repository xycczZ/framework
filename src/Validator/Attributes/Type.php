<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Type extends Rule
{
    public function __construct(
        public string $type,
        public array $scenes = ['default'],
        public string $errorMsg = '',
    )
    {
    }
}