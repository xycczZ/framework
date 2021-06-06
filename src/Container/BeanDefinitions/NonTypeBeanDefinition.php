<?php
declare(strict_types=1);

namespace Xycc\Winter\Container\BeanDefinitions;


use ReflectionClass;
use Xycc\Winter\Container\BeanDefinitionCollection;


class NonTypeBeanDefinition extends AbstractBeanDefinition
{
    public function __construct(string $name, BeanDefinitionCollection $manager)
    {
        $this->name = $name;
        $this->className = null;
        $this->manager = $manager;
    }

    final protected function parseMetadata(ReflectionClass $ref): void
    {
    }

    protected function resolveInstance(array $extra = [])
    {
        $configuration = $this->manager->findDefinitionById($this->configurationId);
        return $this->invokeMethod($configuration->getInstance(), $this->configurationMethod, $extra);
    }
}