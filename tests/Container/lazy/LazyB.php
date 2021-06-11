<?php
declare(strict_types=1);

namespace Xycc\Winter\Tests\Container\lazy;

use Xycc\Winter\Contract\Attributes\Component;

#[Component]
class LazyB
{
    public A $a;

    public function __construct(A $a)
    {
        $this->a = $a;
    }

    public function returnB(): string
    {
        return 'B';
    }
}