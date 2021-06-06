<?php
declare(strict_types=1);

namespace Xycc\Winter\Aspect\Processors;


use Closure;

class AfterReturningProcessor implements ProxyProcessor
{
    use FillOriginMethod;

    public function __construct(private Closure $pointcut)
    {
    }

    /**
     * 返回之后切面， 可以改造返回值
     *
     * @return void
     */
    public function proxy(Closure $passable, Closure $next): mixed
    {
        $result = $next($passable);
        return ($this->pointcut)($this->joinPoint, $result);
    }
}