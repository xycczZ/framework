<?php
declare(strict_types=1);

namespace Xycc\Winter\Tests\Container\boots;


interface TestBeanInterface
{
    public function returnConst(): string;
}