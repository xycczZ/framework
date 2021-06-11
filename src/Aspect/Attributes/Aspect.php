<?php


namespace Xycc\Winter\Aspect\Attributes;

use Attribute;
use Xycc\Winter\Contract\Attributes\Component;

#[Attribute(Attribute::TARGET_CLASS)]
final class Aspect extends Component
{
}