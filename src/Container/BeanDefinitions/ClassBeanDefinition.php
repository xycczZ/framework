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

                $definition = $this->manager->findDefinitionsByType($returnType->getName(), false);
                if ($definition === null) {
                    $definition = new BuiltinBeanDefinition($returnType->getName(), $this->manager);
                }
                $definition->update($configurationMethod, $bean->value ?: $configurationMethod->name);

            } else {
                $definition = $this->manager->findDefinitionByClass($returnType->getName());
                if ($definition === null) {
                    $class = new ReflectionClass($returnType->getName());
                    if ($class->isUserDefined()) {
                        $definition = new self($class->name, new SplFileInfo($class->getFileName()), $this->manager);
                    } else {
                        $definition = new ExtensionBeanDefinition($returnType->getName(), $this->manager);
                    }
                }
                $definition->update($configurationMethod);

            }
            $this->manager->add($definition);
        }
    }

    protected function resolveInstance(array $info, array $extra = [])
    {
        if ($info['configurationClass'] !== null) {
            return $this->invokeConfiguration($info, $extra);
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