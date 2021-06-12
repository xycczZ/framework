<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class EndWith extends Rule
{
    public function __construct(
        public string $end,
        public array $scenes = ['default'],
        public string $errorMsg = '',
    )
    {
    }
}