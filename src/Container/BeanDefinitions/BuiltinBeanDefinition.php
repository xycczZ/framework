<?php
declare(strict_types=1);

namespace Xycc\Winter\Container\BeanDefinitions;


use ReflectionClass;
use Xycc\Winter\Container\BeanDefinitionCollection;

class BuiltinBeanDefinition extends AbstractBeanDefinition
{
    public function __construct(
        string $name,
        string $type,
        BeanDefinitionCollection $manager,
    )
    {
        $this->name = $name;
        $this->className = $type;
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