<?php
declare(strict_types=1);

namespace Xycc\Winter\Aspect\JoinPoints;


use ReflectionMethod;
use ReflectionParameter;

final class MethodSignature
{
    private string $name;
    private string $class;
    private array $params;
    private int $modifier;

    public const IS_PUBLIC = 1;
    public const IS_PROTECTED = 2;
    public const IS_PRIVATE = 4;

    public const IS_STATIC = 16;
    public const IS_FINAL = 32;
    public const IS_ABSTRACT = 64;

    public function __construct(private ReflectionMethod $refMethod)
    {
        $this->class = $this->refMethod->getDeclaringClass()->getName();
        $this->name = $this->refMethod->getName();
        $this->params = $this->refMethod->getParameters();
        $this->modifier = $this->refMethod->getModifiers();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * @return ReflectionParameter[]
     */
    public function getParams(): array
    {
        return $this->params;
    }

    public function getModifier(): int
    {
        return $this->modifier;
    }

    /**
     * 只有public,protected 方法可以被代理
     * 且不能是 final，abstract
     * @return bool
     */
    public function allowProxy(): bool
    {
        return ($this->modifier & (self::IS_PUBLIC | self::IS_PROTECTED))
            && !($this->modifier & (self::IS_FINAL | self::IS_PRIVATE | self::IS_ABSTRACT));
    }

    public function isFinal(): bool
    {
        return ($this->modifier & self::IS_FINAL) === 1;
    }

    public function isAbstract(): bool
    {
        return ($this->modifier & self::IS_ABSTRACT) === 1;
    }

    public function isPublic(): bool
    {
        return ($this->modifier & self::IS_PUBLIC) === 1;
    }

    public function isProtected(): bool
    {
        return ($this->modifier & self::IS_PROTECTED) === 1;
    }

    public function isPrivate(): bool
    {
        return ($this->modifier & self::IS_PRIVATE) === 1;
    }

    public function isStatic(): bool
    {
        return ($this->modifier & self::IS_STATIC) === 1;
    }
}