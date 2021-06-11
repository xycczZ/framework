<?php
declare(strict_types=1);

namespace Xycc\Winter\Tests\Container\lazy;

use Xycc\Winter\Contract\Attributes\Component;
use Xycc\Winter\Contract\Attributes\Lazy;
use Xycc\Winter\Contract\Attributes\Scope;

#[Component]
#[Scope(Scope::SCOPE_PROTOTYPE)]
class APrototype
{
    public function __construct(#[Lazy] public LazyB $b)
    {
    }
}