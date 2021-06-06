<?php
declare(strict_types=1);

namespace Xycc\Winter\Aspect\Expressions;


class ReturnTypeExpr extends AbstractExpr
{
    private array $types = [];

    public function __construct()
    {
    }

    public function parse(string $expr): void
    {
        $this->expr = trim($expr);
        if ($expr === '*') {
            $this->matchAll = true;
        } else {
            $types = explode('|', $expr);
            $this->types = array_map('trim', $types);
        }
    }

    public function match(?array $types): bool
    {
        if ($this->matchAll) {
            return true;
        }

        if ($types === null) {
            return $this->expr === '';
        }

        $ok = 0;
        foreach ($types as $type) {
            $inner = $ok;
            foreach ($this->types as $exprType) {
                if (wildcard($type, $exprType)) {
                    $inner++;
                    break ;
                }
            }

            if ($ok === $inner) {
                return false;
            }
            $ok++;
        }

        return true;
    }
}