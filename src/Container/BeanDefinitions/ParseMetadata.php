<?php
declare(strict_types=1);

namespace Xycc\Winter\Container\BeanDefinitions;


use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use Xycc\Winter\Contract\Attributes\Autowired;
use Xycc\Winter\Contract\Attributes\Bean;
use Xycc\Winter\Contract\Attributes\Configuration;
use Xycc\Winter\Contract\Attributes\Lazy;
use Xycc\Winter\Contract\Attributes\Order;
use Xycc\Winter\Contract\Attributes\Primary;
use Xycc\Winter\Contract\Attributes\Scope;

trait ParseMetadata
{
    // 是否有方法 bean
    protected bool $isConfiguration = false;
    /**@var ReflectionMethod[] 方法 bean 的反射方法列表 */
    protected array $configurationMethods = [];
    /**@var ReflectionMethod[] setter 方法 */
    protected array $setters = [];

    protected bool $bean = false;

    // 类的反射实例
    protected ReflectionClass $refClass;
    /**@var ReflectionMethod[] 方法的反射实例 */
    protected array $refMethods = [];
    /**@var ReflectionProperty[] 属性的反射实例 */
    protected array $refProps = [];
    /**@var ReflectionParameter[][] 方法参数的反射实例 */
    protected array $refParams = [];

    /**@var ReflectionAttribute[] 类的直接注解数组 */
    protected array $classAttributes = [];
    /**@var ReflectionAttribute[][] 方法的直接注解数组 */
    protected array $methodAttributes = [];
    /**@var ReflectionAttribute[][] 属性的直接注解数组 */
    protected array $propertyAttributes = [];
    /**@var ReflectionAttribute[][][] 方法参数的直接注解数组 */
    protected array $paramAttributes = [];

    protected array $allClassAttributes = [];
    protected array $allMethodAttributes = [];
    protected array $allPropertyAttributes = [];
    protected array $allParamAttributes = [];

    protected function parseMetadata(ReflectionClass $ref): void
    {
        $this->handleClassAttrs($ref);

        $props = $ref->getProperties();
        foreach ($props as $prop) {
            $this->refProps[$prop->name] = $prop;
            $this->handlePropAttrs($prop);
        }

        $methods = $ref->getMethods();
        foreach ($methods as $method) {
            $this->refMethods[$method->name] = $method;
            $this->handleMethodAttrs($method);
            $params = $method->getParameters();
            foreach ($params as $param) {
                $this->refParams[$method->name][$param->name] = $param;
                $this->handleParamAttrs($method, $param);
            }
        }
    }

    protected function filterFirstAttribute(array $attributes, string $attr, bool $extends = true): ?ReflectionAttribute
    {
        return current(array_filter(
            $attributes,
            fn (ReflectionAttribute $attribute) => $extends
                ? $this->isSameOrSubClassOf($attr, $attribute->getName())
                : $attr === $attribute->getName()
        )) ?: null;
    }

    protected function handleClassAttrs(ReflectionClass $ref): void
    {
        $this->classAttributes = $ref->getAttributes();
        $this->allClassAttributes = $this->collectAttributes($this->classAttributes, []);
        $bean = $this->filterFirstAttribute($this->allClassAttributes, Bean::class)?->newInstance();
        $scope = $this->filterFirstAttribute($this->allClassAttributes, Scope::class)?->newInstance();
        $lazy = $this->filterFirstAttribute($this->allClassAttributes, Lazy::class);
        $order = $this->filterFirstAttribute($this->allClassAttributes, Order::class)?->newInstance();
        $primary = $this->filterFirstAttribute($this->allClassAttributes, Primary::class);

        $this->bean = $bean !== null;

        if ($bean?->value !== null) {
            $this->manager->addName($bean?->value, $this);
        }

        $this->isConfiguration = !empty(
        array_filter($this->classAttributes,
            fn ($attribute) => $attribute->getName() === Configuration::class)
        );
    }

    // 搜集所有的注解, 每个注解只收集一次
    private function collectAttributes(array $attributes, array $acc)
    {
        foreach ($attributes as $attribute) {
            /**@var ReflectionAttribute $attribute*/
            $attributeClass = $attribute->getName();
            if (!isset($acc[$attributeClass]) && class_exists($attributeClass)) {
                $acc[$attributeClass] = $attribute;
                $attrs = (new ReflectionClass($attributeClass))->getAttributes();
                $acc = $this->collectAttributes($attrs, $acc);
            }
        }
        return $acc;
    }

    protected function handlePropAttrs(ReflectionProperty $property)
    {
        $attributes = $property->getAttributes();
        $this->propertyAttributes[$property->getName()] = $attributes;
        $this->allPropertyAttributes[$property->getName()] = $this->collectAttributes($attributes, []);
    }

    // 魔术方法是否要过滤掉
    protected function handleMethodAttrs(ReflectionMethod $method)
    {
        $attributes = $method->getAttributes();
        $this->methodAttributes[$method->getName()] = $attributes;
        $this->allMethodAttributes[$method->getName()] = $this->collectAttributes($attributes, []);

        if ($this->isConfiguration) {
            if ($this->methodHasAttribute($method->name, Bean::class, true)) {
                $this->configurationMethods[] = $method;
            }
        }

        if ($this->isSetter($method)) {
            $this->setters[] = $method;
        }
    }

    protected function isSetter(ReflectionMethod $method): bool
    {
        if ($method->isStatic() || !$method->isPublic() || $method->isAbstract()) {
            return false;
        }

        if (count($method->getAttributes(Autowired::class)) === 0) {
            return false;
        }

        $name = $method->name;
        if (str_starts_with($name, 'set') && strlen($name) > 3) {
            $prop = lcfirst(substr($name, 3));
            if ($this->refClass->hasProperty($prop)) {
                return true;
            }
        }

        return false;
    }

    protected function handleParamAttrs(ReflectionMethod $method, ReflectionParameter $parameter)
    {
        $attributes = $parameter->getAttributes();
        $this->paramAttributes[$method->getName()][$parameter->getName()] = $attributes;
        $this->allParamAttributes[$method->getName()][$parameter->getName()] = $this->collectAttributes($attributes, []);
    }

    public function isBean(): bool
    {
        return $this->bean;
    }
}