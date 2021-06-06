<?php
declare(strict_types=1);

namespace Xycc\Winter\Contract\Container;


use ReflectionAttribute;

interface ParamInfoContract
{
    /**
     * 获取指定方法的所有 ParameterAttribute
     *
     * @param string $method
     * @return ReflectionAttribute[]
     */
    public function getAllParameterAttributes(string $method): array;

    /**
     * 获取所有的ParameterAttribute
     *
     * @return ReflectionAttribute[]
     */
    public function getParameterAttributes(string $method, string|int|null $param = null): array;

    /**
     * 指定方法的指定参数是否含有指定的 attribute
     *
     * @param string     $method
     * @param string|int $paramNameOrIndex
     * @param string     $attribute
     * @param bool       $extends
     * @return bool
     */
    public function paramHasAttribute(string $method, string|int $paramNameOrIndex,
                                      string $attribute, bool $extends = false): bool;

    /**
     * 获取指定方法含有指定 attribute 的参数
     *
     * @param string $method
     * @param string $attribute
     * @param bool   $extends
     * @return string[]
     */
    public function getParams(string $method, string $attribute, bool $extends = false): array;

    /**
     * 获取指定方法的所有参数的名字
     *
     * @param string $method
     * @return string[]
     */
    public function getMethodParamNames(string $method): array;

    /**
     * 获取指定方法的指定参数的指定注解
     *
     * @param string     $method
     * @param string|int $paramNameOrIndex
     * @param string     $attribute
     * @param bool       $extends
     * @return ReflectionAttribute[]
     */
    public function getParamAttrs(string $method, string|int $paramNameOrIndex,
                                  string $attribute, bool $extends = false): array;
}