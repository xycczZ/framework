<?php
declare(strict_types=1);

namespace Xycc\Winter\Aspect\Expressions;


use Xycc\Winter\Aspect\Exceptions\InvalidExpressionException;

class AccessExpr extends AbstractExpr
{
    private array $accesses = [];

    public function parse(string $expr): void
    {
        $this->expr = trim($expr);

        $this->accesses = array_map('trim', explode('|', $this->expr));
        foreach ($this->accesses as $access) {
            if (!in_array($access, ['public', 'protected', 'private'])) {
                throw new InvalidExpressionException('访问修饰符只能是 public, protected, private');
            }
        }

        if (in_array('public', $this->accesses)
            && in_array('protected', $this->accesses)
            && in_array('private', $this->accesses)) {
            $this->matchAll = true;
        }
    }

    public function match(string $expr): bool
    {
        if ($this->matchAll) {
            return true;
        }

        $exprs = array_map('trim', explode('|', $expr));
        foreach ($exprs as $expression) {
            if (! in_array($expression, $this->accesses)) {
                return false;
            }
        }

        return true;
    }
}