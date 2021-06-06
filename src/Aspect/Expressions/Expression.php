<?php
declare(strict_types=1);

namespace Xycc\Winter\Aspect\Expressions;


use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Xycc\Winter\Aspect\Exceptions\InvalidExpressionException;

/**
 * represent ONE pointcut expression
 * include Attribute, Attribute Arguments, Namespace, Class, Method, Params, ReturnType
 */
class Expression
{
    private string $expr;
    private bool $all = false;

    private AccessExpr $accessExpr;
    private ClassExpr $classExpr;
    private MethodExpr $methodExpr;
    private ReturnTypeExpr $returnTypeExpr;

    public function __construct(string $expr)
    {
        $this->expr = trim($expr);

        $this->accessExpr = new AccessExpr();
        $this->classExpr = new ClassExpr();
        $this->methodExpr = new MethodExpr();
        $this->returnTypeExpr = new ReturnTypeExpr();

        $this->parse();
    }

    public function isMatchAll(): bool
    {
        return $this->all;
    }

    public function matchAccess(string|int $access): bool
    {
        $access = match ($access) {
            ReflectionMethod::IS_PUBLIC => 'public',
            ReflectionMethod::IS_PROTECTED => 'protected',
            ReflectionMethod::IS_PRIVATE => 'private',
            'public' | 'protected' | 'private' => $access,
            default => throw new InvalidExpressionException('访问修饰符只能是[public|protected|private]')
        };

        return $this->all || $this->accessExpr->match($access);
    }

    public function matchClass(string $class): bool
    {
        return $this->all || $this->classExpr->match($class);
    }

    public function matchMethod(ReflectionMethod $method): bool
    {
        return $this->all || $this->methodExpr->match($method);
    }

    public function matchReturnType(?ReflectionType $type): bool
    {
        if ($this->all) {
            return true;
        }

        if ($type instanceof ReflectionNamedType) {
            $types = [$type->getName()];
        } elseif ($type instanceof ReflectionUnionType) {
            $types = array_map(fn ($type) => $type->getName(), $type->getTypes());
        } else {
            $types = null;
        }

        return $this->returnTypeExpr->match($types);
    }

    public function parse()
    {
        $rest = $this->expr;

        // 通配
        if ($rest === '*') {
            $this->all = true;
            return ;
        }

        [$acc, $rest] = explode(' ', $rest, 2);
        $this->accessExpr->parse($acc);

        // 类名
        if (str_contains($rest, '::')) {
            [$nsAndClass, $rest] = explode('::', $rest);
            $this->classExpr->parse($nsAndClass);
        }

        // 方法
        if (str_contains($rest, '(') && str_contains($rest, ')')) {
            [$methodName, $rest] = explode('(', $rest);
            [$params, $rest] = explode(')', $rest);
            $this->methodExpr->parse($methodName. '%' . $params);
        }

        // 返回值
        if (str_contains($rest, ':')) {
            [, $return] = explode(':', $rest);
            $this->returnTypeExpr->parse($return);
        }
    }
}