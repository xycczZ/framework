<?php
declare(strict_types=1);

namespace Xycc\Winter\Aspect\JoinPoints;

use ReflectionMethod;
use Xycc\Winter\Aspect\Processors\AroundProcessor;

class ProceedingJoinPoint extends JoinPoint
{
    public function __construct(ReflectionMethod $method, protected AroundProcessor $processor)
    {
        parent::__construct($method);
    }

    public function proceed(): mixed
    {
        return $this->processor->done();
    }
}