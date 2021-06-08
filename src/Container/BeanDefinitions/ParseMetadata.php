<?php
declare(strict_types=1);

namespace Xycc\Winter\Container\BeanDefinitions;


use JetBrains\PhpStorm\ExpectedValues;
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

    #[ExpectedValues(flags: Scope::SCOPES)]
    protected int $scope;
    #[ExpectedValues(flags: Scope::MODES)]
    protected int $scopeMode;
    protected bool $lazyInit;
    protected int $order;
    protected bool $primary;

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

    protected function filterFirstAttribute(array $attributes, string $attr): ?ReflectionAttribute
    {
        return current(array_filter($attributes, fn (ReflectionAttribute $attribute) => $this->isSameOrSubClassOf($attribute->getName(), $attr))) ?: null;
    }

    protected function handleClassAttrs(ReflectionClass $ref): void
    {
        $this->classAttributes = $ref->getAttributes();
        $this->allClassAttributes = $this->collectAttributes($this->classAttributes, []);
        $bean = $this->filterFirstAttribute($this->allClassAttributes, Bean::class);
        $scope = $this->filterFirstAttribute($this->allClassAttributes, Scope::class);
        $lazy = $this->filterFirstAttribute($this->allClassAttributes, Lazy::class);
        $order = $this->filterFirstAttribute($this->allClassAttributes, Order::class);
        $primary = $this->filterFirstAttribute($this->allClassAttributes, Primary::class);

        $this->primary = $primary !== null;
        $this->order = $order?->newInstance()?->value ?: Order::DEFAULT;
        $scopeInstance = $scope?->newInstance();
        $this->scope = $scopeInstance?->scope ?: Scope::SCOPE_SINGLETON;
        $this->scopeMode = $scopeInstance?->mode ?: SCope::MODE_DEFAULT;
        $this->lazyInit = $lazy !== null;
        $this->name = $bean?->newInstance()?->value;

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
            $beans = $method->getAttributes(Bean::class, ReflectionAttribute::IS_INSTANCEOF);
            if (count($beans) > 0) {
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
}