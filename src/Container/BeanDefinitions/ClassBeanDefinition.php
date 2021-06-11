<?php
declare(strict_types=1);

namespace Xycc\Winter\Container\BeanDefinitions;


use ReflectionClass;
use ReflectionNamedType;
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
        $this->canProxy = $this->refClass->isInstantiable() && !$this->refClass->isFinal();
    }

    public function setUpConfiguration(): array
    {
        if (!$this->isConfiguration) {
            return [];
        }

        $defs = [];
        foreach ($this->configurationMethods as $configurationMethod) {

            $returnType = $configurationMethod->getReturnType();
            /**@var ?ReflectionNamedType $returnType */
            $returnType = $this->getRefType($returnType);

            $bean = $this->getMethodAttributes(
                $configurationMethod->name, Bean::class, true
            )[0]->newInstance();

            if ($returnType === null) {
                $definition = new NonTypeBeanDefinition($bean->value ?: $configurationMethod->name, $this->manager);

            } elseif ($returnType->isBuiltin()) {

                $definition = $this->manager->getDefByClass($returnType->getName());
                if ($definition === null) {
                    $definition = new BuiltinBeanDefinition($returnType->getName(), $this->manager);
                }

            } else {
                $definition = $this->manager->getDefByClass($returnType->getName());
                if ($definition === null) {
                    $class = new ReflectionClass($returnType->getName());
                    if ($class->isUserDefined()) {
                        $definition = new self($class->name, new SplFileInfo($class->getFileName()), $this->manager);
                    } else {
                        $definition = new ExtensionBeanDefinition($returnType->getName(), $this->manager);
                    }
                }
            }
            $defs[] = ['def' => $definition, 'method' => $configurationMethod];
            $this->manager->add($definition);
        }

        return $defs;
    }
}