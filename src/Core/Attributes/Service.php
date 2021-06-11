<?php
declare(strict_types=1);

namespace Xycc\Winter\Core\Attributes;


use Attribute;
use Xycc\Winter\Contract\Attributes\Component;

#[Attribute(Attribute::TARGET_CLASS)]
final class Service extends Component
{
}