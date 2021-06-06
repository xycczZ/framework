<?php
declare(strict_types=1);

namespace Xycc\Winter\Aspect\JoinPoints;


use ReflectionMethod;

class JoinPoint
{
    // 原方法的签名
    protected MethodSignature $signature;

    // 实际调用的参数
    protected array $args = [];

    // 执行结果，在 before 方法执行完之前，此属性保持 null
    protected mixed $result = null;

    // 原方法
    protected ReflectionMethod $method;

    /**
     * 将要调用的切面类方法
     * key => class, value => methodName[]
     * @var array<array<string>>
     */
    protected array $methods = [];

    public function __construct(ReflectionMethod $method)
    {
        $this->method = $method;
        $this->signature = new MethodSignature($method);
    }

    public function getSignature(): MethodSignature
    {
        return $this->signature;
    }

    public function getArgs(): array
    {
        return $this->args;
    }

    public function getResult(): mixed
    {
        return $this->result;
    }
}