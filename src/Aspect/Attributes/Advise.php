<?php


namespace Xycc\Winter\Aspect\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
abstract class Advise
{
    public array $pointcuts;

    /**
     * 如果表达式以()结尾，删去，只保留方法名
     * 只能设置一个切面类内的切点方法
     */
    public function __construct(string ...$pointcuts)
    {
        $expr = array_map('trim', $pointcuts);

        $this->pointcuts = array_map(
            fn (string $pointcut) => str_ends_with($pointcut, '()')
                ? trim(substr($pointcut, 0, strlen($pointcut) - 2))
                : $pointcut,
            $expr);
    }
}