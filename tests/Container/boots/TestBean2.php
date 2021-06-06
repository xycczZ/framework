<?php
declare(strict_types=1);

namespace Xycc\Winter\Tests\Container\boots;


use Xycc\Winter\Contract\Attributes\Bean;

#[Bean]
class TestBean2 implements TestBeanInterface
{
    public const Xxx = 'testBean2';

    public function returnConst(): string
    {
        return self::Xxx;
    }
}