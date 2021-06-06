<?php
declare(strict_types=1);

namespace Xycc\Winter\Container\BeanDefinitions;

use ReflectionAttribute;

trait PropInfo
{
    /**@var ReflectionAttribute[][] 属性的注解数组 */
    protected array $propertyAttributes = [];

    /**
     * @return ReflectionAttribute[]
     */
    public function getAllPropertyAttributes(): array
    {
        return $this->propertyAttributes;
    }

    /**
     * 获取含有指定 attribute 的属性
     *
     * @param string $attribute
     * @param bool   $extends
     * @return string[]
     */
    public function getProps(string $attribute, bool $extends = false): array
    {
        $props = $this->getPropNames();
        return array_filter($props, fn (string $prop) => $this->propHasAttribute($prop, $attribute, $extends));
    }

    /**
     * @return string[]
     */
    public function getPropNames(): array
    {
        return array_keys($this->propertyAttributes);
    }

    /**
     * 指定属性是否包含有指定的 attribute
     *
     * @param string $prop
     * @param string $attribute
     * @param bool   $extends
     * @return bool
     */
    public function propHasAttribute(string $prop, string $attribute, bool $extends = false): bool
    {
        return count($this->getPropAttrs($prop, $attribute, $extends)) > 0;
    }

    /**
     * 获取指定属性的所有 attribute
     *
     * @param string $prop
     * @param string $attribute
     * @param bool   $extends
     * @return ReflectionAttribute[]
     */
    public function getPropAttrs(string $prop, string $attribute, bool $extends = false): array
    {
        if (!isset($this->propertyAttributes[$prop])) {
            return [];
        }

        return $this->filterAttribute($this->propertyAttributes[$prop], $attribute, $extends);
    }
}