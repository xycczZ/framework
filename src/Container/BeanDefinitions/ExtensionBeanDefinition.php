<?php
declare(strict_types=1);

namespace Xycc\Winter\Container\BeanDefinitions;

use ReflectionClass;
use SplFileInfo;
use Xycc\Winter\Container\BeanDefinitionCollection;

class ExtensionBeanDefinition extends AbstractBeanDefinition
{
    public function __construct(
        string $className, // 类名
        BeanDefinitionCollection $manager,
    )
    {
        $this->className = $className;
        $this->refClass = new ReflectionClass($this->className);
        $this->manager = $manager;
        $this->parseMetadata($this->refClass);
    }

    public function getFile(): ?SplFileInfo
    {
        return null;
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