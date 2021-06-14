<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Validation
{
    public function __construct(
        public bool $fastFail = false
    )
    {
    }
}