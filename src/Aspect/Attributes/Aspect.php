<?php


namespace Xycc\Winter\Aspect\Attributes;

use Attribute;
use Xycc\Winter\Contract\Attributes\Component;
use Xycc\Winter\Contract\Attributes\NoProxy;

#[Attribute(Attribute::TARGET_CLASS)]
#[NoProxy]
final class Aspect extends Component
{
}