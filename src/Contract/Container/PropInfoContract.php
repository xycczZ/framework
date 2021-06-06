<?php
declare(strict_types=1);

namespace Xycc\Winter\Contract\Container;


use ReflectionAttribute;

interface PropInfoContract
{
    /**
     * @return ReflectionAttribute[]
     */
    public function getAllPropertyAttributes(): array;

    /**
     * 获取指定属性的所有 attribute
     *
     * @param string $prop
     * @param string $attribute
     * @param bool   $extends
     * @return ReflectionAttribute[]
     */
    public function getPropAttrs(string $prop, string $attribute, bool $extends = false): array;

    /**
     * 指定属性是否包含有指定的 attribute
     *
     * @param string $prop
     * @param string $attribute
     * @param bool   $extends
     * @return bool
     */
    public function propHasAttribute(string $prop, string $attribute, bool $extends = false): bool;

    /**
     * 获取含有指定 attribute 的属性
     *
     * @param string $attribute
     * @param bool   $extends
     * @return string[]
     */
    public function getProps(string $attribute, bool $extends = false): array;

    /**
     * @return string[]
     */
    public function getPropNames(): array;
}