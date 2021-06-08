<?php
declare(strict_types=1);

namespace Xycc\Winter\Container\BeanDefinitions;

use ReflectionAttribute;
use ReflectionParameter;

trait ParamInfo
{
    /**@var ReflectionAttribute[][][] 方法参数的注解数组 */
    protected array $paramAttributes = [];

    /**@var ReflectionParameter[][] 方法参数的反射实例 */
    protected array $refParams = [];

    protected array $allParamAttributes = [];

    /**
     * 获取指定方法的所有 ParameterAttribute
     *
     * @param string $method
     * @return ReflectionAttribute[]
     */
    public function getAllParameterAttributes(string $method, bool $direct = false): array
    {
        $prop = $direct ? 'paramAttributes' : 'allParamAttributes';
        return $this->{$prop}[$method] ?? [];
    }

    /**
     * 获取所有的ParameterAttribute
     *
     * @return ReflectionAttribute[]
     */
    public function getParameterAttributes(string $method, string|int|null $param = null, bool $direct = false): array
    {
        $prop = $direct ? 'paramAttributes' : 'allParamAttributes';

        if ($param === null) {
            return $this->{$prop}[$method];
        }

        if (is_int($param)) {
            $name = $this->convertPositionToName($method, $param);
        } else {
            $name = $param;
        }

        return $this->{$prop}[$method][$name] ?? [];
    }

    private function convertPositionToName(string $method, int $position): ?string
    {
        $refParam = first($this->refParams[$method], fn (ReflectionParameter $parameter) => $parameter->getPosition() === $position);
        return $refParam?->name;
    }

    /**
     * 获取指定方法含有指定 attribute 的参数
     *
     * @param string $method
     * @param string $attribute
     * @param bool   $extends
     * @return string[]
     */
    public function getParams(string $method, string $attribute, bool $extends = false, bool $direct = false): array
    {
        $params = $this->getMethodParamNames($method);
        return array_filter($params, fn (string $param) => $this->paramHasAttribute($method, $param, $attribute, $extends, $direct));
    }

    /**
     * 获取指定方法的所有参数的名字
     *
     * @param string $method
     * @return string[]
     */
    public function getMethodParamNames(string $method): array
    {
        return array_map(fn (ReflectionParameter $param) => $param->name, $this->refParams[$method]);
    }

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
                                      string $attribute, bool $extends = false, bool $direct = false): bool
    {
        return count($this->getParamAttrs($method, $paramNameOrIndex, $attribute, $extends, $direct)) > 0;
    }

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
                                  string $attribute, bool $extends = false, bool $direct = false): array
    {
        $name = $this->convertPositionToName($method, $paramNameOrIndex);

        $prop = $direct ? 'paramAttributes' : 'allParamAttributes';
        $paramAttrs = $this->{$prop}[$method][$name] ?? [];

        if (count($paramAttrs) < 1) {
            return [];
        }


        return $this->filterAttribute($paramAttrs, $attribute, $extends);
    }
}