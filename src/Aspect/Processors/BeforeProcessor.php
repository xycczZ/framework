<?php
declare(strict_types=1);

namespace Xycc\Winter\Aspect\Processors;


use Closure;

class BeforeProcessor implements ProxyProcessor
{
    use FillOriginMethod;

    public function __construct(private Closure $pointcut)
    {
    }

    /**
     * 前置切面， 只能拿到参数
     * 不能有返回值
     *
     * @return void
     */
    public function proxy(Closure $passable, Closure $next): mixed
    {
        ($this->pointcut)($this->joinPoint);
        return $next($passable);
    }
}