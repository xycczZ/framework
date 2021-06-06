<?php
declare(strict_types=1);

namespace Xycc\Winter\Aspect\Processors;


use Closure;
use Exception;

class AfterThrowingProcessor implements ProxyProcessor
{
    use FillOriginMethod;

    private string $exception;

    public function __construct(
        private Closure $pointcut,
    )
    {
    }

    /**
     * 异常切点, 返回值代替原有的返回值
     *
     * @return void
     * @throws Exception
     */
    public function proxy(Closure $passable, Closure $next): mixed
    {
        try {
            $next($passable);
        } catch (Exception $e) {
            return ($this->pointcut)($this->joinPoint, $e);
        }
    }
}