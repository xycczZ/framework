<?php
declare(strict_types=1);

namespace Xycc\Winter\Aspect\Expressions;


abstract class AbstractExpr
{
    protected bool $matchAll = false;
    protected string $expr = '';

    public function isMatchAll(): bool
    {
        return $this->matchAll;
    }

    public function getExpr(): string
    {
        return $this->expr;
    }

    public abstract function parse(string $expr): void;
}