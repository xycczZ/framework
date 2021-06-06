<?php


namespace Xycc\Winter\Aspect\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class AfterReturning extends Advise
{
}