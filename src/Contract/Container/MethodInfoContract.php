<?php
declare(strict_types=1);

namespace Xycc\Winter\Contract\Container;


use ReflectionAttribute;

interface MethodInfoContract
{
    /**
     * @return ReflectionAttribute[]
     */
    public function getAllMethodAttributes(string $method = ''): array;

    /**
     * 获取指定方法上的所有指定的 attribute
     *
     * @param string $method
     * @param string $attribute
     * @param bool   $extends
     * @return ReflectionAttribute[]
     */
    public function getMethodAttributes(string $method, string $attribute, bool $extends = false): array;

    /**
     * 指定方法是否包含有指定的 attribute
     *
     * @param string $method
     * @param string $attribute
     * @param bool   $extends
     * @return bool
     */
    public function methodHasAttribute(string $method, string $attribute, bool $extends = false): bool;

    /**
     * 获取含有指定 attribute 的方法
     *
     * @param string $attribute
     * @param bool   $extends
     * @return string[]
     */
    public function getMethods(string $attribute, bool $extends = false): array;

    /**
     * @return string[]
     */
    public function getMethodNames(): array;
}