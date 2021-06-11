<?php


namespace Xycc\Winter\Config\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final class Value
{
    public function __construct(public string $path)
    {
    }
}