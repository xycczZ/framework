<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[ValidateAllData]
#[Attribute(Attribute::TARGET_PROPERTY)]
class Present extends Rule
{
}