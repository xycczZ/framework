<?php
declare(strict_types=1);

namespace Xycc\Winter\Container\BeanDefinitions;

use ReflectionAttribute;

trait ClassInfo
{
    /**@var ReflectionAttribute[] attributes of current class */
    protected array $classAttributes = [];

    // bean 的类型，对于`ClassBeanDefinition`是类名
    // 对于`NonTypeBeanDefinition`是 null
    // 对于`BuiltinBeanDefinition`是内置类型名称
    // 对于 ExtensionBeanDefinition 是扩展的类名
    /**@readonly current class name */
    protected ?string $className;

    /**
     * @return ReflectionAttribute[]
     */
    public function getAllClassAttributes(): array
    {
        return $this->classAttributes;
    }

    /**
     * this class contains the specified attribute?
     *
     * @param string $attribute
     * @param bool   $extends
     * @return bool
     */
    public function classHasAttribute(string $attribute, bool $extends = false): bool
    {
        return count($this->getClassAttributes($attribute, $extends)) > 0;
    }

    /**
     * get all specified attributes of current class
     *
     * @param string $attribute Attribute class FQN, used to filter attributes
     * @param bool   $extends   determines whether need to search for subclasses
     * @return ReflectionAttribute[]
     */
    public function getClassAttributes(string $attribute, bool $extends = false): array
    {
        return $this->filterAttribute($this->classAttributes, $attribute, $extends);
    }
}