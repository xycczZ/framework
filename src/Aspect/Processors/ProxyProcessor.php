<?php
declare(strict_types=1);

namespace Xycc\Winter\Aspect\Processors;


use Closure;
use ReflectionMethod;

interface ProxyProcessor
{
    /**
     * 调用切点方法
     * 环绕、returning、throwing切面需要获取返回值
     * 前置、后置切面不能获取返回值
     *
     * @param Closure $passable 最终要执行的方法
     * @param Closure $next     执行链中的下一个 ▶️
     *
     * @return mixed 方法返回值
     */
    public function proxy(Closure $passable, Closure $next): mixed;

    public function setOriginMethod(ReflectionMethod $method): static;
}