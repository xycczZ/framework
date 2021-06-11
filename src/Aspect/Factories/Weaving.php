<?php


namespace Xycc\Winter\Aspect\Factories;


use Closure;
use Xycc\Winter\Contract\Attributes\Autowired;

trait Weaving
{
    #[Autowired]
    private ProxyFactory $__FACTORY__;

    private function __getProxyClosure__(string $method): Closure
    {
        return $this->__FACTORY__->getProxy(self::__ID__, $method);
    }
}