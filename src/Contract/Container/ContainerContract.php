<?php


namespace Xycc\Winter\Contract\Container;


use Psr\Container\ContainerInterface;
use Xycc\Winter\Container\BeanDefinitions\AbstractBeanDefinition;


interface ContainerContract extends ContainerInterface
{
    public function get($id, ?string $type = null);

    public function has($id): bool;

    public function getRootPath(): string;

    public function publishFiles(string $filePath, string $toPath = ''): bool;

    /**
     * @return AbstractBeanDefinition[]
     */
    public function getClassesByAttr(string $attr, bool $extends = false): array;

    /**
     * @return string[]
     */
    public function getMethodsByAttr(string $class, string $attr, bool $extends = false): array;

    /**
     * @return string[]
     */
    public function getPropsByAttr(string $class, string $attr, bool $extends = false): array;

    /**
     * @return string[]
     */
    public function getParamsByAttr(string $class, string $method, string $attr, bool $extends = false): array;

    public function execute($action, array $extra = []);
}