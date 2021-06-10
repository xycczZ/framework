<?php
declare(strict_types=1);

namespace Xycc\Winter\Container\Components;


use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;

class AttributeParser
{
    private static array $classAttributes = [];
    private static array $propAttributes = [];
    private static array $methodAttributes = [];
    private static array $paramAttributes = [];

    public static function parseClass(string $class)
    {
        if (isset(self::$classAttributes[$class])) {
            return self::$classAttributes[$class];
        }

        $ref = new ReflectionClass($class);
        $attributes = self::collectAttributes($ref->getAttributes(), []);
        return self::$classAttributes[$class] = $attributes;
    }

    public static function collectAttributes(array $attributes, array $acc = []): array
    {
        foreach ($attributes as $attribute) {
            /**@var ReflectionAttribute $attribute */
            $attributeClass = $attribute->getName();
            if (!isset($acc[$attributeClass]) && class_exists($attributeClass)) {
                $acc[$attributeClass] = $attribute;
                $attrs = (new ReflectionClass($attributeClass))->getAttributes();
                $acc = self::collectAttributes($attrs, $acc);
            }
        }
        return $acc;
    }

    public static function parseProp(string $class, string $prop)
    {
        if (isset(self::$propAttributes[$class][$prop])) {
            return self::$propAttributes[$class][$prop];
        }

        $ref = new ReflectionProperty($class, $prop);
        $attributes = self::collectAttributes($ref->getAttributes(), []);
        return self::$propAttributes[$class][$prop] = $attributes;
    }

    public static function parseMethod(string $class, string $method)
    {
        if (isset(self::$methodAttributes[$class][$method])) {
            return self::$methodAttributes[$class][$method];
        }

        $ref = new ReflectionMethod($class, $method);
        $attributes = self::collectAttributes($ref->getAttributes());
        return self::$methodAttributes[$class][$method] = $attributes;
    }

    // 搜集所有的注解, 每个注解只收集一次

    public static function parseParam(string $class, string $method, string $param)
    {
        if (isset(self::$paramAttributes[$class][$method][$param])) {
            return self::$paramAttributes[$class][$method][$param];
        }

        $ref = new ReflectionParameter([$class, $method], $param);
        $attributes = self::collectAttributes($ref->getAttributes());
        return self::$paramAttributes[$class][$method][$param] = $attributes;
    }

    /**
     * @param ReflectionAttribute[] $attributes
     */
    public static function getAttribute(array $attributes, string $attr, array $acc = []): ?ReflectionAttribute
    {
        foreach ($attributes as $attribute) {
            $attributeClass = $attribute->getName();

            if ($attributeClass === $attr) {
                return $attribute;
            }

            if (!isset($acc[$attributeClass]) && class_exists($attributeClass)) {
                $acc[$attributeClass] = true;
                $attrs = (new ReflectionClass($attributeClass))->getAttributes();
                $acc = self::getAttribute($attrs, $attr, $acc);
            }
        }

        return null;
    }
}