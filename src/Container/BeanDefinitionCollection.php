<?php
declare(strict_types=1);

namespace Xycc\Winter\Container;

use Xycc\Winter\Container\BeanDefinitions\AbstractBeanDefinition;
use Xycc\Winter\Container\BeanDefinitions\NonTypeBeanDefinition;
use Xycc\Winter\Container\Exceptions\DuplicatedIdentityException;
use Xycc\Winter\Container\Proxy\ProxyManager;
use Xycc\Winter\Contract\Attributes\Component;
use Xycc\Winter\Contract\Attributes\NoProxy;


#[Component('beanManager')]
#[NoProxy]
class BeanDefinitionCollection
{
    /**
     * @var AbstractBeanDefinition[]
     */
    private array $coll = [];

    public ProxyManager $proxyManager;

    private array $classes = [];

    public function add(AbstractBeanDefinition $definition)
    {
        $className = $definition->getClassName();

        if ($this->hasClass($className)) {
            throw new DuplicatedIdentityException($definition->getClassName());
        }
        $this->coll[] = $definition;
        if ($className) {
            $this->classes[$className] = true;
        }
    }

    public function hasClass(?string $className)
    {
        if (!$className) {
            return false;
        }

        return isset($this->classes[$className]);
    }

    public function getDefByClass(string $className): ?AbstractBeanDefinition
    {
        return current(array_filter($this->coll, fn (AbstractBeanDefinition $def) => $def->getClassName() === $className)) ?: null;
    }

    public function getDefByName(string $name): ?AbstractBeanDefinition
    {
        return current(array_filter($this->coll, fn (AbstractBeanDefinition $def) => $def->getName() === $name)) ?: null;
    }

    public function getNonType(string $name)
    {
        return current(array_filter($this->coll, fn (AbstractBeanDefinition $def) => $def instanceof NonTypeBeanDefinition && $def->getName() === $name)) ?: null;
    }

    /**
     * @return AbstractBeanDefinition[]
     */
    public function filterDefinitions(callable $fn): array
    {
        return array_values(array_filter($this->coll, $fn));
    }

    public function all(): array
    {
        return $this->coll;
    }

    public function getClassesByAttr(string $attribute, bool $extends = false, bool $direct = false): array
    {
        return $this->filterDefinitions(
            fn (AbstractBeanDefinition $definition) => $definition->classHasAttribute($attribute, $extends, $direct)
        );
    }
}