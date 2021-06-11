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
        $this->canProxy = $this->isInstantiable($type);
        $this->manager = $manager;
    }

    protected function isInstantiable(string $type): bool
    {
        if (!class_exists($type)) {
            return false;
        }
        $ref = new ReflectionClass($type);
        return $ref->isInstantiable() && !$ref->isFinal();
    }

    final protected function parseMetadata(ReflectionClass $ref): void
    {
    }
}