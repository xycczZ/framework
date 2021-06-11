<?php
declare(strict_types=1);

namespace Xycc\Winter\Contract\Container;


use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use SplFileInfo;


interface BeanDefinitionContract extends ClassInfoContract, MethodInfoContract, PropInfoContract, ParamInfoContract
{
    /**
     * 是否含有方法 bean
     *
     * @return bool
     */
    public function isConfiguration(): bool;

    public function haveConfigurationMethods(): bool;

    /**
     *  获取类型名称
     */
    public function getClassName(): ?string;

    /**
     *  获取所有的 setter ， 检查是否需要注入
     */
    public function getSetters(): array;

    /**
     *  获取工厂方法
     */
    public function getConfigurationMethods(): array;

    /**
     *  获取类所在文件的信息
     */
    public function getFile(): ?SplFileInfo;

    public function getRefClass(): ReflectionClass;

    public function getRefMethod(string $method = ''): array|null|ReflectionMethod;

    public function getRefProp(string $prop = ''): array|null|ReflectionProperty;

    public function getRefParams(string $method, string|int|null $paramNameOrIndex = null): array|null|ReflectionParameter;
}