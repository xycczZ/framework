<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Ip extends Rule
{
    public function __construct(
        public bool $v6 = false,
        public array $scenes = ['default'],
        public string $errorMsg = '',
    )
    {
    }
}