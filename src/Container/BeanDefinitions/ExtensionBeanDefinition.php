<?php
declare(strict_types=1);

namespace Xycc\Winter\Container\BeanDefinitions;

use ReflectionClass;
use ReflectionException;
use SplFileInfo;
use Xycc\Winter\Container\BeanDefinitionCollection;

class ExtensionBeanDefinition extends AbstractBeanDefinition
{
    /**
     * @throws ReflectionException
     */
    public function __construct(
        string $className, // 类名
        BeanDefinitionCollection $manager,
    ) {
        $this->className = $className;
        $this->refClass  = new ReflectionClass($this->className);
        $this->canProxy  = $this->refClass->isInstantiable() && !$this->refClass->isFinal();
        $this->manager   = $manager;
        $this->parseMetadata($this->refClass);
    }

    public function getFile(): ?SplFileInfo
    {
        return null;
    }
}