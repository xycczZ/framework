<?php


namespace Xycc\Winter\Aspect\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Pointcut
{
    public string $expr;

    public function __construct(string $expr)
    {
        $this->expr = $expr;
    }
}