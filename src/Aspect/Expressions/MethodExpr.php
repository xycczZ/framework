<?php
declare(strict_types=1);

namespace Xycc\Winter\Aspect\Expressions;


class MethodExpr extends AbstractExpr
{
    /**
     * @var ParamExpr[]
     */
    private array $paramExprs = [];
    private bool $matchAllParams = false;

    public function __construct()
    {
    }

    public function parse(string $expr): void
    {
        [$method, $param] = explode('%', $expr);
        $this->expr = trim($method);
        $param = trim($param);

        if ($method === '*') {
            $this->matchAll = true;
        }

        if ($param === '*') {
            $this->matchAllParams = true;
        } elseif ($param !== '') {
            $params = explode(',', $param);
            foreach ($params as $param) {
                $paramExpr = new ParamExpr();
                $paramExpr->parse($param);
                $this->paramExprs[] = $paramExpr;
            }
        }
    }

    public function match(\ReflectionMethod $refMethod): bool
    {
        if ($this->matchAll) {
            return true;
        }

        return wildcard($refMethod->name, $this->expr)
            && ($this->matchAllParams || $this->matchParams($refMethod->getParameters()));
    }

    /**
     * @param \ReflectionParameter[] $refParam
     * @return bool
     */
    private function matchParams(array $refParam): bool
    {
        foreach ($this->paramExprs as $index => $paramExpr) {
            $paramInfo = [
                'type' => $this->getTypeName($refParam[$index]->getType()),
                'name' => $refParam[$index]->getName(),
                'defaultValue' => $refParam[$index]->isOptional() ? $refParam[$index]->getDefaultValue() : null,
            ];
            if (! $paramExpr->match($paramInfo)) {
                return false;
            }
        }
        return true;
    }

    private function getTypeName(?\ReflectionType $type): ?array
    {
        if ($type instanceof \ReflectionNamedType) {
            return [$type->getName()];
        } elseif ($type instanceof \ReflectionUnionType) {

            $result = [];
            foreach ($type->getTypes() as $type) {
                $result[] = $type->getName();
            }

            return $result;
        } else {
            return null;
        }
    }
}