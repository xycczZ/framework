<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[ValidateAllData]
#[Attribute(Attribute::TARGET_PROPERTY)]
class Sometimes extends Rule
{
}