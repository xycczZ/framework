<?php
declare(strict_types=1);

namespace Xycc\Winter\Container\BeanDefinitions;


use ReflectionClass;
use Xycc\Winter\Container\BeanDefinitionCollection;


class NonTypeBeanDefinition extends AbstractBeanDefinition
{
    public function __construct(string $name, BeanDefinitionCollection $manager)
    {
        $this->className = null;
        $this->canProxy = true; // 直接生成匿名对象
        $this->manager = $manager;
    }

    final protected function parseMetadata(ReflectionClass $ref): void
    {
    }
}