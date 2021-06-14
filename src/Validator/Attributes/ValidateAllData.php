<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

/**
 * Verify first.
 * The parameter passed in is not the value of a parameter, but all the values
 */
#[Attribute(Attribute::TARGET_CLASS)]
class ValidateAllData
{
}