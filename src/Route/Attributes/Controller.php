<?php
declare(strict_types=1);

namespace Xycc\Winter\Route\Attributes;

use Attribute;
use Xycc\Winter\Contract\Attributes\Bean;

#[Attribute(Attribute::TARGET_CLASS)]
class Controller extends Bean
{
    public function __construct(public ?string $value = null, public string $path = '')
    {
    }
}