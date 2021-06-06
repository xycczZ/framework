<?php
declare(strict_types=1);

namespace Xycc\Winter\Tests\Container\lazy;

use Xycc\Winter\Contract\Attributes\Bean;
use Xycc\Winter\Contract\Attributes\Lazy;
use Xycc\Winter\Contract\Attributes\Scope;

#[Bean]
#[Scope(Scope::SCOPE_PROTOTYPE)]
class APrototype
{
    public function __construct(#[Lazy] public LazyB $b)
    {
    }
}