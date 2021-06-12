<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;
use Xycc\Winter\Contract\Attributes\Component;

#[Attribute(Attribute::TARGET_CLASS)]
class Validator extends Component
{
    /**
     * Validator is used to annotate a class for custom rule validation.
     */
    public function __construct(
        public string $rule,
        public string $scene = 'default',
        public ?string $value = null,
    )
    {
    }
}