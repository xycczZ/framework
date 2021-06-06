<?php
declare(strict_types=1);

namespace Xycc\Winter\Tests\Container\lazy;


use Xycc\Winter\Contract\Attributes\Bean;
use Xycc\Winter\Contract\Attributes\Lazy;

#[Bean]
class A
{
    public LazyB $b;

    public function __construct(#[Lazy] LazyB $b)
    {
        $this->b = $b;
    }

    public function returnA()
    {
        return 'A';
    }
}