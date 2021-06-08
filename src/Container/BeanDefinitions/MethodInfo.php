<?php
declare(strict_types=1);

namespace Xycc\Winter\Container\BeanDefinitions;

use ReflectionAttribute;

trait MethodInfo
{
    /**@var ReflectionAttribute[][] 方法的注解数组 */
    protected array $methodAttributes = [];

    protected array $allMethodAttributes = [];

    /**
     * @return ReflectionAttribute[]
     */
    public function getAllMethodAttributes(string $methodName = '', bool $direct = false): array
    {
        if ($methodName) {
            return $direct ? $this->methodAttributes[$methodName] : $this->allMethodAttributes[$methodName];
        }
        return $direct ? $this->methodAttributes : $this->allMethodAttributes;
    }

    /**
     * 获取含有指定 attribute 的方法
     *
     * @param string $attribute
     * @param bool   $extends
     * @return string[]
     */
    public function getMethods(string $attribute, bool $extends = false, bool $direct = false): array
    {
        $methods = $this->getMethodNames();
        return array_filter($methods, fn (string $method) => $this->methodHasAttribute($method, $attribute, $extends, $direct));
    }

    /**
     * @return string[]
     */
    public function getMethodNames(): array
    {
        return array_keys($this->allMethodAttributes);
    }

    /**
     * 指定方法是否包含有指定的 attribute
     *
     * @param string $method
     * @param string $attribute
     * @param bool   $extends
     * @return bool
     */
    public function methodHasAttribute(string $method, string $attribute, bool $extends = false, bool $direct = false): bool
    {
        return count($this->getMethodAttributes($method, $attribute, $extends, $direct)) > 0;
    }

    /**
     * 获取指定方法上所有指定的 attribute
     *
     * @param string $method
     * @param string $attribute
     * @param bool   $extends
     * @return ReflectionAttribute[]
     */
    public function getMethodAttributes(string $method, string $attribute, bool $extends = false, bool $direct = false): array
    {
        $prop = $direct ? 'methodAttributes' : 'allMethodAttributes';
        if (!isset($this->{$prop}[$method])) {
            return [];
        }

        return $this->filterAttribute($this->{$prop}[$method], $attribute, $extends);
    }
}