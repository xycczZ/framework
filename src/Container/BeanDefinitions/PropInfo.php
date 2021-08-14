<?php
declare(strict_types=1);

namespace Xycc\Winter\Container\BeanDefinitions;

use ReflectionAttribute;

trait PropInfo
{
    /**@var ReflectionAttribute[][] 属性的注解数组 */
    protected array $propertyAttributes = [];

    protected array $allPropertyAttributes = [];

    /**
     * @return ReflectionAttribute[]
     */
    public function getAllPropertyAttributes(bool $direct = false): array
    {
        return $direct ? $this->propertyAttributes : $this->allPropertyAttributes;
    }

    /**
     * 获取含有指定 attribute 的属性
     *
     * @return string[]
     */
    public function getProps(string $attribute, bool $extends = false, bool $direct = false): array
    {
        $props = $this->getPropNames();
        return array_filter($props, fn (string $prop) => $this->propHasAttribute($prop, $attribute, $extends, $direct));
    }

    /**
     * @return string[]
     */
    public function getPropNames(): array
    {
        return array_keys($this->allPropertyAttributes);
    }

    /**
     * 指定属性是否包含有指定的 attribute
     */
    public function propHasAttribute(string $prop, string $attribute, bool $extends = false, bool $direct = false): bool
    {
        return count($this->getPropAttrs($prop, $attribute, $extends, $direct)) > 0;
    }

    /**
     * 获取指定属性的所有 attribute
     *
     * @return ReflectionAttribute[]
     */
    public function getPropAttrs(string $prop, string $attribute, bool $extends = false, bool $direct = false): array
    {
        if (!isset($this->allPropertyAttributes[$prop])) {
            return [];
        }

        return $this->filterAttribute(
            $direct
                ? $this->propertyAttributes[$prop]
                : $this->allPropertyAttributes[$prop],
            $attribute,
            $extends
        );
    }
}