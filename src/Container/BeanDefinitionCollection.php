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
use Xycc\Winter\Contract\Attributes\Scope;

#[Bean('beanManager')]
class BeanDefinitionCollection
{
    /**
     * @var AbstractBeanDefinition[]
     */
    private array $coll = [];

    private static array $semi = [];

    public ProxyManager $proxyManager;

    private array $names = [];

    public function add(AbstractBeanDefinition $definition)
    {
        if ($definition->getClassName() !== null && $this->hasClass($definition->getClassName())) {
            throw new DuplicatedIdentityException($definition->getClassName());
        }
        $this->coll[] = $definition;
    }

    public function findDefinitionByClass(string $class, bool $isBean = true): ?AbstractBeanDefinition
    {
        return current($this->filterDefinitions(fn (AbstractBeanDefinition $definition) => $definition->getClassName() === $class && (!$isBean || $definition->isBean())));
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

        $beans = [];
        foreach ($defs as $def) {
            $names = $def->getNames();
            foreach ($names as $name => $info) {
                $beans[] = $info + ['def' => $def, 'name' => $name];
            }
        }

        $primary = array_filter($beans, fn (array $info) => $info['primary']);
        if (count($primary) === 1) {
            return current($primary);
        } elseif (count($primary) > 1) {
            throw new MultiPrimaryException(implode(', ', array_map(fn (array $info) => $info['name'], $primary)));
        }

        usort($beans, fn ($a, $b) => $a['order'] <=> $b['order']);
        $beans = array_values($beans);
        if ($beans[0]['order'] === $beans[1]['order']) {
            throw new PriorityDecidedException(implode(', ', array_map(fn ($info) => $info['name'], array_filter($beans, fn ($bean) => $bean['order'] === $beans[0]['order']))));
        }
        return $beans[0];
    }

    /**
     * @return AbstractBeanDefinition[]
     */
    public function filterDefinitions(callable $fn): array
    {
        return array_values(array_filter($this->coll, $fn));
    }

    public function findDefinitionByName(string $name): ?AbstractBeanDefinition
    {
        return $this->names[$name] ?? null;
    }

    public function all(): array
    {
        return $this->coll;
    }

    public function hasClass(string $class, bool $isBean = false): bool
    {
        return $this->findDefinitionByClass($class, $isBean) !== null;
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
        foreach ($this->coll as $def) {
            if ($def->isBean()) {
                foreach ($def->getNames() as $name => $info) {
                    if ($info['scope'] === Scope::SCOPE_REQUEST) {
                        $def->clearRequest($requestId, $name);
                    }
                }
            }
        }
    }

    public function clearSession(int $sessionId)
    {
        foreach ($this->coll as $def) {
            if ($def->isBean()) {
                foreach ($def->getNames() as $name => $info) {
                    if ($info['scope'] === Scope::SCOPE_SESSION) {
                        $def->clearSession($sessionId, $name);
                    }
                }
            }
        }
    }

    public function addName(string $name, AbstractBeanDefinition $def)
    {
        if (isset($this->names[$name])) {
            throw new DuplicatedIdentityException($def->getClassName(), [$name]);
        }
        $this->names[$name] = $def;
    }
}