<?php
declare(strict_types=1);

namespace Xycc\Winter\Aspect\Processors;


use Closure;

class AfterProcessor implements ProxyProcessor
{
    use FillOriginMethod;

    public function __construct(
        private Closure $pointcut, // 要执行的切点方法
    )
    {
    }

    /**
     * 后置增强
     */
    public function proxy(Closure $passable, Closure $next): mixed
    {
        $result = $next($passable);
        ($this->pointcut)($this->joinPoint);
        return $result;
    }
}