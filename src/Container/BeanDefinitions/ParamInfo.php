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

    /**
     * 获取指定方法的所有 ParameterAttribute
     *
     * @param string $method
     * @return ReflectionAttribute[]
     */
    public function getAllParameterAttributes(string $method): array
    {
        return $this->paramAttributes[$method] ?? [];
    }

    /**
     * 获取所有的ParameterAttribute
     *
     * @return ReflectionAttribute[]
     */
    public function getParameterAttributes(string $method, string|int|null $param = null): array
    {
        if ($param === null) {
            return $this->paramAttributes[$method];
        }

        if (is_int($param)) {
            $name = $this->convertPositionToName($method, $param);
        } else {
            $name = $param;
        }

        return $this->paramAttributes[$method][$name] ?? [];
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
    public function getParams(string $method, string $attribute, bool $extends = false): array
    {
        $params = $this->getMethodParamNames($method);
        return array_filter($params, fn (string $param) => $this->paramHasAttribute($method, $param, $attribute, $extends));
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
                                      string $attribute, bool $extends = false): bool
    {
        return count($this->getParamAttrs($method, $paramNameOrIndex, $attribute, $extends)) > 0;
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
                                  string $attribute, bool $extends = false): array
    {
        $name = $this->convertPositionToName($method, $paramNameOrIndex);

        $paramAttrs = $this->paramAttributes[$method][$name] ?? [];

        if (count($paramAttrs) < 1) {
            return [];
        }


        return $this->filterAttribute($paramAttrs, $attribute, $extends);
    }
}