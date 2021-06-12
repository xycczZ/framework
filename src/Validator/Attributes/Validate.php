<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Validate
{
    public function __construct(public string $scene = 'default')
    {
    }
}