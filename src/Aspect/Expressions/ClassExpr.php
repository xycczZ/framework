<?php
declare(strict_types=1);

namespace Xycc\Winter\Aspect\Expressions;


use Xycc\Winter\Aspect\Exceptions\InvalidExpressionException;

class ClassExpr extends AbstractExpr
{
    private string $namespace = '';
    private string $class = '';

    public function __construct()
    {
    }

    public function parse(string $expr): void
    {
        $this->expr = trim($expr);
        if (str_contains($this->expr, '\\')) {
            throw new InvalidExpressionException('The namespace is separated by ".", do not use "\\"');
        }

        if (str_contains($this->expr, '.')) {
            $pos = strrpos($this->expr, '.');
            $this->namespace = substr($this->expr, 0, $pos);
            $this->class = substr($this->expr, $pos + 1);
        } else {
            $this->class = $this->expr;
        }
    }

    public function match(string $fqn): bool
    {
        $fqn = str_replace('\\', '.', $fqn);
        return $this->matchAll || $this->matchRegex($fqn);
    }

    private function matchRegex(string $fqn): bool
    {
        if (!str_contains($this->expr, '*')) {
            return $fqn === $this->expr;
        }
        // 表达式中有*号，需要正则匹配， 将命名空间的分隔符 . 转义
        $expr = str_replace('.', '\.', $this->expr);
        return wildcard($fqn, $expr);
    }
}