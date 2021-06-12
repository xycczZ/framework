<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;
use Closure;

#[BeforeValidate]
#[Attribute(Attribute::TARGET_PROPERTY)]
class NotEmpty extends Rule
{
    public function __construct(
        public bool|Closure|null $if = null,
        public array $scenes = ['default'],
        public string $errorMsg = '',
    )
    {
    }
}