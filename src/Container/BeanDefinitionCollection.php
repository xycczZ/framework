<?php
declare(strict_types=1);

namespace Xycc\Winter\Container;

use Xycc\Winter\Container\BeanDefinitions\AbstractBeanDefinition;
use Xycc\Winter\Container\Exceptions\CycleDependencyException;
use Xycc\Winter\Container\Exceptions\DuplicatedIdentityException;
use Xycc\Winter\Container\Exceptions\MultiPrimaryException;
use Xycc\Winter\Container\Exceptions\NotFoundException;
use Xycc\Winter\Container\Exceptions\PriorityDecidedException;
use Xycc\Winter\Container\Proxy\ProxyManager;
use Xycc\Winter\Contract\Attributes\Bean;

#[Bean('beanManager')]
class BeanDefinitionCollection
{
    /**
     * @var AbstractBeanDefinition[]
     */
    private array $coll = [];

    private static array $semi = [];

    public ProxyManager $proxyManager;

    public function add(AbstractBeanDefinition $definition)
    {
        if (isset($this->coll[$definition->getId()])) {
            throw new DuplicatedIdentityException($definition->getClassName(), $definition->getName());
        }
        $this->coll[$definition->getId()] = $definition;
    }

    public function findDefinitionsByClass(string $class, bool $isBean = true): array
    {
        return $this->filterDefinitions(fn (AbstractBeanDefinition $definition) => $definition->getClassName() === $class && (!$isBean || $definition->isBean()));
    }

    public function findDefinitionsByType(string $abstract, bool $isBean = true): array
    {
        return $this->filterDefinitions(fn (AbstractBeanDefinition $def) => ($def->getClassName() === $abstract || is_subclass_of($def->getClassName(), $abstract) && (!$isBean || $def->isBean())));
    }

    public function findHighestPriorityDefinitionByType(string $abstract): AbstractBeanDefinition
    {
        $defs = $this->findDefinitionsByType($abstract, true);
        if (count($defs) === 1) {
            return $defs[0];
        } elseif (count($defs) === 0) {
            throw new NotFoundException($abstract);
        }

        $primary = array_filter($defs, fn (AbstractBeanDefinition $def) => $def->isPrimary());
        if (count($primary) === 1) {
            return current($primary);
        } elseif (count($primary) > 1) {
            throw new MultiPrimaryException(implode(', ', array_map(fn (AbstractBeanDefinition $def) => $def->getId(), $primary)));
        }

        usort($defs, fn ($a, $b) => $a->getOrder() <=> $b->geOrder());
        $defs = array_values($defs);
        if ($defs[0]->getOrder() === $defs[1]->getOrder()) {
            throw new PriorityDecidedException(implode(', ', array_map(fn ($def) => $def->getId(), array_filter($defs, fn ($def) => $def->getOrder() === $defs[0]->getOrder()))));
        }
        return $defs[0];
    }

    /**
     * @return AbstractBeanDefinition[]
     */
    public function filterDefinitions(callable $fn): array
    {
        return array_values(array_filter($this->coll, $fn));
    }

    public function findDefinitionById(string $id): ?AbstractBeanDefinition
    {
        return current(
            $this->filterDefinitions(fn (AbstractBeanDefinition $definition) => $definition->getId() === $id)
        ) ?: null;
    }

    public function findDefinitionByName(string $name): ?AbstractBeanDefinition
    {
        return current(
            $this->filterDefinitions(fn (AbstractBeanDefinition $definition) => $definition->getName() === $name)
        ) ?: null;
    }

    public function all(): array
    {
        return $this->coll;
    }

    public function hasClass(string $class): bool
    {
        return count($this->findDefinitionsByClass($class)) > 0;
    }

    public function getClassesByAttr(string $attribute, bool $extends = false, bool $direct = false): array
    {
        return $this->filterDefinitions(
            fn (AbstractBeanDefinition $definition) => $definition->classHasAttribute($attribute, $extends, $direct)
        );
    }

    /**
     * @return string[]
     */
    public function getMethodsByAttr(string $class, string $attribute, bool $extends = false, bool $direct = false): array
    {
        return filter_map($this->coll,
            fn (AbstractBeanDefinition $definition) => $definition->getClassName() !== $class ? []
                : array_values(array_filter($definition->getMethodNames(),
                    fn (string $method) => $definition->methodHasAttribute($method, $attribute, $extends, $direct))), [])[$class];
    }

    /**
     * @return string[]
     */
    public function getPropsByAttr(string $class, string $attribute, bool $extends = false, bool $direct = false): array
    {
        return filter_map($this->coll,
            fn (AbstractBeanDefinition $definition) => $definition->getClassName() !== $class ? []
                : array_values(array_filter($definition->getPropNames(),
                    fn (string $prop) => $definition->propHasAttribute($prop, $attribute, $extends, $direct))), [])[$class];
    }

    /**
     * @return string[]
     */
    public function getParamsByAttr(string $class, string $method, string $attribute, bool $extends = false, bool $direct = false): array
    {
        return filter_map($this->coll,
            fn (AbstractBeanDefinition $definition) => $definition->getClassName() !== $class ? []
                : array_values(array_filter($definition->getMethodParamNames($method),
                    fn (string $param) => $definition->paramHasAttribute($method, $param, $attribute, $extends, $direct))), [])[$class][$method];
    }

    public static function appendSemi(string $id)
    {
        if (in_array($id, self::$semi)) {
            $semi = self::$semi;
            self::$semi = [];
            throw new CycleDependencyException('Cycle dependency! Use #[Lazy] to make it late init', $semi);
        }
        self::$semi[] = $id;
    }

    public static function popSemi()
    {
        array_pop(self::$semi);
    }

    public function clearRequest(int $requestId)
    {
        $this->coll = array_map(function (AbstractBeanDefinition $def) use ($requestId) {
            if ($def->isRequest()) {
                $def->clearRequest($requestId);
            }
            return $def;
        }, $this->coll);
    }

    public function clearSession(int $sessionId)
    {
        $this->coll = array_map(function (AbstractBeanDefinition $def) use ($sessionId) {
            $def->clearSession($sessionId);
            return $def;
        }, $this->coll);
    }
}