<?php


namespace Xycc\Winter\Container\Proxy;


use Xycc\Winter\Container\Factory\BeanInfo;


trait LazyObject
{
    private static BeanInfo $__BEAN_INFO__;

    public static function __initLazyObject__(BeanInfo $definition)
    {
        self::$__BEAN_INFO__ = $definition;
    }

    /**
     * 判断是否有对象存在，有对象存在就直接调用对象的方法
     * 如果没有对象存在，就创建一个新的
     */
    public function __callOriginMethodAndReplaceSelf__($method, ...$args)
    {
        $instance = self::$__BEAN_INFO__->getInstance();
        return $instance->{$method}(...$args);
    }
}