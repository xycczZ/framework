<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class StartWith extends Rule
{
    public function __construct(
        public string $start,
        public array $scenes = ['default'],
        public string $errorMsg = '',
    )
    {
    }
}