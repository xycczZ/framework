<?php
declare(strict_types=1);

namespace Xycc\Winter\Container\BeanDefinitions;


use ReflectionClass;
use Xycc\Winter\Container\BeanDefinitionCollection;


class NonTypeBeanDefinition extends AbstractBeanDefinition
{
    private string $name;

    public function __construct(string $name, BeanDefinitionCollection $manager)
    {
        $this->name = $name;
        $this->className = null;
        $this->manager = $manager;
        $this->manager->addName($name, $this);
    }

    public function getName()
    {
        return $this->name;
    }

    final protected function parseMetadata(ReflectionClass $ref): void
    {
    }

    protected function resolveInstance(array $info, array $extra = [])
    {
        return $this->invokeConfiguration($info, $extra);
    }
}