<?php
declare(strict_types=1);

namespace Xycc\Winter\Container\BeanDefinitions;


use ReflectionClass;
use Xycc\Winter\Container\BeanDefinitionCollection;

class BuiltinBeanDefinition extends AbstractBeanDefinition
{
    public function __construct(
        string $type,
        BeanDefinitionCollection $manager,
    )
    {
        $this->className = $type;
        $this->manager = $manager;
    }

    final protected function parseMetadata(ReflectionClass $ref): void
    {
    }

    protected function resolveInstance(array $info, array $extra = [])
    {
        return $this->invokeConfiguration($info, $extra);
    }
}