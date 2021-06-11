<?php


namespace Xycc\Winter\Http\Attributes;

use Attribute;
use Xycc\Winter\Contract\Attributes\Component;

#[Attribute(Attribute::TARGET_CLASS)]
class ExceptionHandler extends Component
{
}