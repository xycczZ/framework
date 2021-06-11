<?php
declare(strict_types=1);

namespace Xycc\Winter\Tests\Container\lazy;


use Xycc\Winter\Contract\Attributes\Component;
use Xycc\Winter\Contract\Attributes\Lazy;

#[Component]
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