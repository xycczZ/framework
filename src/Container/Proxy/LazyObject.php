<?php


namespace Xycc\Winter\Container\Proxy;


use Xycc\Winter\Container\Factory\BeanFactory;


trait LazyObject
{
    private string $__BEAN_NAME__;
    private BeanFactory $__BEAN_FACTORY__;

    public function __SET_BEAN_INFO__(string $name, BeanFactory $factory): static
    {
        $this->__BEAN_NAME__    = $name;
        $this->__BEAN_FACTORY__ = $factory;
        return $this;
    }

    /**
     * 判断是否有对象存在，有对象存在就直接调用对象的方法
     * 如果没有对象存在，就创建一个新的
     */
    public function __callOriginMethod__($method, ...$args)
    {
        $instance = $this->__BEAN_FACTORY__->get($this->__BEAN_NAME__);
        return $instance->{$method}(...$args);
    }
}