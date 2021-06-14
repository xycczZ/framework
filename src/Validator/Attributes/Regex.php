<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Regex extends Rule
{
    public function __construct(
        public string $regex,
        public bool $not = false,
        public array $scenes = ['default'],
        public string $errorMsg = '',
    )
    {
    }
}