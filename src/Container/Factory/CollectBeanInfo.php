<?php
declare(strict_types=1);

namespace Xycc\Winter\Container\Factory;


use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Xycc\Winter\Container\BeanDefinitionCollection;
use Xycc\Winter\Container\BeanDefinitions\AbstractBeanDefinition;
use Xycc\Winter\Container\Exceptions\DuplicatedIdentityException;
use Xycc\Winter\Container\Exceptions\InvalidBindingException;
use Xycc\Winter\Contract\Attributes\Bean;
use Xycc\Winter\Contract\Attributes\Component;
use Xycc\Winter\Contract\Attributes\Lazy;
use Xycc\Winter\Contract\Attributes\Order;
use Xycc\Winter\Contract\Attributes\Primary;
use Xycc\Winter\Contract\Attributes\Scope;

trait CollectBeanInfo
{
    protected BeanDefinitionCollection $manager;
    public array                       $beans = [];

    public function addBean(AbstractBeanDefinition $def, ?ReflectionMethod $method = null, ?AbstractBeanDefinition $origin = null)
    {
        if (null !== $method) {
            $this->addMethodBean($method, $def, $origin);
            return;
        }

        $this->addClassBean($def);
    }

    private function addMethodBean(ReflectionMethod $method, AbstractBeanDefinition $conf, AbstractBeanDefinition $def)
    {
        $bean = $this->getFirstMethodAttr($conf, $method->name, Bean::class, true);
        if ($bean === null) {
            throw new InvalidBindingException(sprintf('Bean %s must have #[Bean] attribute', $method->getReturnType()));
        }

        $name = $bean->value ?: $method->name;

        if (isset($this->beans[$name])) {
            throw new DuplicatedIdentityException($conf->getClassName(), $bean->value);
        }

        $info = $this->collectMethodBeanBaseInfo($conf, $method);

        // Bean which defined on method maybe have no type, so it have no definitions
        $this->beans[$name] = new BeanInfo($name, $info['order'], $info['primary'], $info['lazy'], $info['scope'], $info['scopeMode'], $def, true, $conf->getName(), $method->name);
    }

    private function getFirstMethodAttr(AbstractBeanDefinition $def, string $method, string $attribute, bool $extends = false)
    {
        $attr = $def->getMethodAttributes($method, $attribute, $extends);
        return (current($attr) ?: null)?->newInstance();
    }

    private function parseRefType(?ReflectionType $type): ?ReflectionNamedType
    {
        if ($type instanceof ReflectionUnionType) {
            throw new InvalidBindingException('Union types must not appear in the container');
        }
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $type;
    }

    protected function createBeanName(Component $bean, ?string $type): string
    {
        if ($bean->value) {
            return $bean->value;
        }
        return $type ?? throw new InvalidBindingException(sprintf('Bean must have a type or a unique name, class: %s', $type));
    }

    protected function collectMethodBeanBaseInfo(AbstractBeanDefinition $def, ReflectionMethod $method): array
    {
        $methodName = $method->name;
        $order = $this->getFirstMethodAttr($def, $methodName, Order::class)?->value ?: Order::DEFAULT;
        $lazy = $def->methodHasAttribute($methodName, Lazy::class);
        $primary = $def->methodHasAttribute($methodName, Primary::class);
        $scopeAttr = $this->getFirstMethodAttr($def, $methodName, Scope::class);
        $scope = $scopeAttr?->scope ?: Scope::SCOPE_SINGLETON;
        $scopeMode = $scopeAttr?->mode ?: Scope::MODE_DEFAULT;

        return compact('order', 'lazy', 'primary', 'scope', 'scopeMode');
    }

    protected function addClassBean(AbstractBeanDefinition $def)
    {
        $bean = $this->getFirstClassAttr($def, Component::class, true);
        if ($bean === null) {
            throw new InvalidBindingException(sprintf('Bean %s must have #[Component] attribute', $def->getClassName()));
        }

        $name = $this->createBeanName($bean, $def->getClassName());

        if (isset($this->beans[$name])) {
            throw new DuplicatedIdentityException($def->getClassName(), $bean->value);
        }

        $info = $this->collectClassBeanBaseInfo($def);

        $this->beans[$name] = new BeanInfo($name, $info['order'], $info['primary'], $info['lazy'], $info['scope'], $info['scopeMode'], $def, false);
    }

    private function getFirstClassAttr(AbstractBeanDefinition $def, string $attribute, bool $extends = false)
    {
        $attr = $def->getClassAttributes($attribute, $extends);
        return (current($attr) ?: null)?->newInstance();
    }

    protected function collectClassBeanBaseInfo(AbstractBeanDefinition $def): array
    {
        $order = $this->getFirstClassAttr($def, Order::class)?->value ?: Order::DEFAULT;
        $lazy = $def->classHasAttribute(Lazy::class);
        $primary = $def->classHasAttribute(Primary::class);
        $scopeAttr = $this->getFirstClassAttr($def, Scope::class);
        $scope = $scopeAttr?->scope ?: Scope::SCOPE_SINGLETON;
        $scopeMode = $scopeAttr?->mode ?: Scope::MODE_DEFAULT;

        return compact('order', 'lazy', 'primary', 'scope', 'scopeMode');
    }

    public function setPredefinedInstance(string $name, AbstractBeanDefinition $def, $instance)
    {
        $this->beans[$name] = new BeanInfo($name, Order::DEFAULT, true, false, Scope::SCOPE_SINGLETON, Scope::MODE_DEFAULT, $def, false, instance: $instance);
    }
}