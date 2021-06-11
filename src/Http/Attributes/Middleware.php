<?php


namespace Xycc\Winter\Http\Attributes;

use Attribute;
use Xycc\Winter\Contract\Attributes\Component;

#[Attribute(Attribute::TARGET_CLASS)]
abstract class Middleware extends Component
{
    public function __construct(
        public ?string $value = null,
        public string $group = 'default',
        // all = true 时，会忽略group
        public bool $all = false,
    )
    {
    }
}