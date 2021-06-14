<?php


namespace Xycc\Winter\Database\Attributes;

use Attribute;
use Xycc\Winter\Contract\Attributes\Component;

#[Attribute(Attribute::TARGET_CLASS)]
final class Repository extends Component
{
}