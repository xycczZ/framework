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

    /**@var ReflectionAttribute[] 类的注解数组 */
    protected array $classAttributes = [];
    /**@var ReflectionAttribute[][] 方法的注解数组 */
    protected array $methodAttributes = [];
    /**@var ReflectionAttribute[][] 属性的注解数组 */
    protected array $propertyAttributes = [];
    /**@var ReflectionAttribute[][][] 方法参数的注解数组 */
    protected array $paramAttributes = [];

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

    protected function handleClassAttrs(ReflectionClass $ref): void
    {
        $this->classAttributes = $ref->getAttributes();
        $beans = $ref->getAttributes(Bean::class, ReflectionAttribute::IS_INSTANCEOF);
        $bean = (current($beans) ?: null)?->newInstance();
        $scope = (current($ref->getAttributes(Scope::class)) ?: null)?->newInstance();
        $lazy = count($ref->getAttributes(Lazy::class)) > 0;
        $order = (current($ref->getAttributes(Order::class)) ?: null)?->newInstance();
        $primary = count($ref->getAttributes(Primary::class)) > 0;

        $this->primary = $primary;
        $this->order = $order?->value ?: Order::DEFAULT;
        $this->scope = $scope?->scope ?: Scope::SCOPE_SINGLETON;
        $this->scopeMode = $scope?->mode ?: Scope::MODE_DEFAULT;
        $this->lazyInit = $lazy ?: false;
        $this->name = $bean?->value ?: null;

        $this->isConfiguration = !empty(
        array_filter($this->classAttributes,
            fn ($attribute) => $attribute->getName() === Configuration::class)
        );
    }

    protected function handlePropAttrs(ReflectionProperty $property)
    {
        $this->propertyAttributes[$property->getName()] = $property->getAttributes();
    }

    // 魔术方法是否要过滤掉
    protected function handleMethodAttrs(ReflectionMethod $method)
    {
        $this->methodAttributes[$method->getName()] = $method->getAttributes();

        if ($this->isConfiguration) {
            $beans = $method->getAttributes(Bean::class);
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
        $this->paramAttributes[$method->getName()][$parameter->getName()] = $parameter;
    }
}