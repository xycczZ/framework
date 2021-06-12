<?php
declare(strict_types=1);

namespace Xycc\Winter\Contract\Components;


use Attribute;
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

    public static function getClassAttrs(string $class, string $attr): array
    {
        return array_values(array_filter(self::parseClass($class),
            fn (ReflectionAttribute $attribute) => $attribute->getName() === $attr
                || is_subclass_of($attribute->getName(), $attr)));
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

    public static function getPropAttrs(string $class, string $prop, string $attr): array
    {
        return array_values(array_filter(self::parseProp($class, $prop),
            fn (ReflectionAttribute $attribute) => $attribute->getName() === $attr
                || is_subclass_of($attribute->getName(), $attr)));
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

    public static function getMethodAttrs(string $class, string $method, string $attr): array
    {
        return array_values(array_filter(self::parseMethod($class, $method),
            fn (ReflectionAttribute $attribute) => $attribute->getName() === $attr
                || is_subclass_of($attribute->getName(), $attr)));
    }

    public static function parseParam(string $class, string $method, string $param)
    {
        if (isset(self::$paramAttributes[$class][$method][$param])) {
            return self::$paramAttributes[$class][$method][$param];
        }

        $ref = new ReflectionParameter([$class, $method], $param);
        $attributes = self::collectAttributes($ref->getAttributes());
        return self::$paramAttributes[$class][$method][$param] = $attributes;
    }

    public static function getParamAttrs(string $class, string $method, string $param, string $attr): array
    {
        return array_values(array_filter(self::parseParam($class, $method, $param),
            fn (ReflectionAttribute $attribute) => $attribute->getName() === $attr
                || is_subclass_of($attribute->getName(), $attr)));
    }

    /**
     * @param ReflectionAttribute[] $attributes
     */
    public static function getAttribute(array $attributes, string $attr, array $acc = []): ?ReflectionAttribute
    {
        foreach ($attributes as $attribute) {
            $attributeClass = $attribute->getName();

            if ($attributeClass === $attr || is_subclass_of($attributeClass, $attr)) {
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

    public static function hasAttribute(array $attributes, string $attr): bool
    {
        foreach ($attributes as $attribute) {
            $class = $attribute->getName();

            if ($class === $attr || is_subclass_of($class, $attr)) {
                return true;
            }

            if ($class !== Attribute::class) {
                $attrs = (new ReflectionClass($class))->getAttributes();
                if (self::hasAttribute($attrs, $attr)) {
                    return true;
                }
            }
        }

        return false;
    }
}