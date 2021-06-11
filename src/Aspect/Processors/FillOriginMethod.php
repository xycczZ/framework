<?php
declare(strict_types=1);

namespace Xycc\Winter\Aspect\Processors;


use ReflectionMethod;
use Xycc\Winter\Aspect\JoinPoints\JoinPoint;

trait FillOriginMethod
{
    private ReflectionMethod $originMethod;
    private JoinPoint $joinPoint;

    public function setOriginMethod(ReflectionMethod $method): static
    {
        $self = new self($this->pointcut);
        $self->originMethod = $method;
        $self->joinPoint = new JoinPoint($method);
        //$this->originMethod = $method;
        //$this->joinPoint = new JoinPoint($method);
        return $self;
    }
}