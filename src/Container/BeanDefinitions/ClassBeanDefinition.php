<?php
declare(strict_types=1);

namespace Xycc\Winter\Container\BeanDefinitions;


use ReflectionClass;
use ReflectionNamedType;
use ReflectionUnionType;
use RuntimeException;
use SplFileInfo;
use Xycc\Winter\Container\BeanDefinitionCollection;
use Xycc\Winter\Contract\Attributes\Bean;
use Xycc\Winter\Contract\Attributes\Lazy;
use Xycc\Winter\Contract\Attributes\Order;
use Xycc\Winter\Contract\Attributes\Primary;
use Xycc\Winter\Contract\Attributes\Scope;


class ClassBeanDefinition extends AbstractBeanDefinition
{
    public function __construct(string $type, SplFileInfo $fileInfo, BeanDefinitionCollection $manager)
    {
        $this->fileInfo = $fileInfo;
        $this->className = $type;
        $this->manager = $manager;
        $this->refClass = new ReflectionClass($this->className);
        $this->parseMetadata($this->refClass);

        if ($this->isConfiguration) {
            $this->setUpConfiguration();
        }
    }

    protected function setUpConfiguration()
    {
        foreach ($this->configurationMethods as $configurationMethod) {
            $returnType = $configurationMethod->getReturnType();
            /**@var ?ReflectionNamedType $returnType */
            if ($returnType instanceof ReflectionUnionType) {
                throw new RuntimeException('bean 的类型不能是联合类型');
            }

            $bean = $this->getMethodAttributes(
                $configurationMethod->name, Bean::class, true
            )[0]->newInstance();

            if ($returnType === null) {
                $definition = new NonTypeBeanDefinition($bean->value ?: $configurationMethod->name, $this->manager);
            } elseif ($returnType->isBuiltin()) {
                $definition = new BuiltinBeanDefinition(
                    $bean->value ?: $configurationMethod->name, $returnType->getName(), $this->manager
                );
            } else {
                $class = new ReflectionClass($returnType->getName());
                if ($class->isUserDefined()) {
                    $definition = new self($class->name, new SplFileInfo($class->getFileName()), $this->manager);
                } else {
                    $definition = new ExtensionBeanDefinition($returnType->getName(), $this->manager);
                }
            }

            $lazy = count($this->getMethodAttributes($configurationMethod->name, Lazy::class)) > 0;
            $order = (current($this->getMethodAttributes($configurationMethod->name, Order::class)) ?: null)?->newInstance()?->value ?: Order::DEFAULT;
            $primary = count($this->getMethodAttributes($configurationMethod->name, Primary::class)) > 0;
            $scope = (current($this->getMethodAttributes($configurationMethod->name, Scope::class)) ?: null)?->newInstance();
            $definition->setLazyInit($lazy);
            $definition->setOrder($order);
            $definition->setPrimary($primary);
            $definition->setScope($scope?->scope ?: Scope::SCOPE_SINGLETON);
            $definition->setScopeMode($scope?->mode ?: Scope::MODE_DEFAULT);
            $definition->setters = [];
            $definition->fromConfiguration = true;
            $definition->configurationId = $this->getId() ?: '';
            $definition->configurationMethod = $configurationMethod->name;
            $definition->name = $bean->value ?: $configurationMethod->name;
            $this->manager->add($definition);
        }
    }

    protected function resolveInstance(array $extra = [])
    {
        if ($this->isFromConfiguration()) {
            $configuration = $this->manager->findDefinitionById($this->configurationId);
            return $this->invokeMethod($configuration->getInstance(), $this->configurationMethod);
        }

        $constructor = $this->refClass->getConstructor();
        if ($constructor === null) {
            return $this->refClass->newInstanceWithoutConstructor();
        }
        $params = $constructor->getParameters();
        $args = $this->getMethodArgs($params, $extra);

        return $this->refClass->newInstanceArgs($args);
    }
}