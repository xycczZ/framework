<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[BeforeValidate]
#[Attribute(Attribute::TARGET_PROPERTY)]
class Sometimes extends Rule
{
}