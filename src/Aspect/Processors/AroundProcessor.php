<?php
declare(strict_types=1);

namespace Xycc\Winter\Aspect\Processors;


use Closure;
use ReflectionMethod;
use Xycc\Winter\Aspect\JoinPoints\ProceedingJoinPoint;

class AroundProcessor implements ProxyProcessor
{
    private ProceedingJoinPoint $joinPoint;
    private Closure $passable;
    private Closure $next;

    public function __construct(
        private Closure $pointcut,
    )
    {
    }

    public function setOriginMethod(ReflectionMethod $method): static
    {
        $this->joinPoint = new ProceedingJoinPoint($method, $this);
        return $this;
    }

    /**
     * 环绕通知
     * 先调用通知方法
     * 然后等待在通知方法中调用 ProceedingJoinPoint::proceed()
     * 调用之后， 调用此 Processor 的 done 方法， 执行next
     * 最后获取到返回值返回给通知方法，继续执行
     */
    public function proxy(Closure $passable, Closure $next): mixed
    {
        $this->passable = $passable;
        $this->next = $next;
        return ($this->pointcut)($this->joinPoint);
    }

    public function done(): mixed
    {
        return ($this->next)($this->passable);
    }
}