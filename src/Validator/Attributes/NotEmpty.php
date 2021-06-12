<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class NotEmpty extends ValidationRule
{
    public function __construct(
        public bool|\Closure|null $if = null,
        public string $scene = '',
        public string $errorMsg = '',
    )
    {
    }
}