<?php


namespace Xycc\Winter\Container\Factory;


use JetBrains\PhpStorm\ExpectedValues;
use RuntimeException;
use Swoole\Coroutine;
use Xycc\Winter\Container\BeanDefinitions\AbstractBeanDefinition;
use Xycc\Winter\Container\Proxy\LazyObject;
use Xycc\Winter\Contract\Attributes\Scope;

class BeanInfo
{
    public function __construct(
        private string $name,
        private int $order,
        private bool $primary,
        private bool $lazy,
        #[ExpectedValues(flags: Scope::SCOPES)]
        private int $scope,
        #[ExpectedValues(flags: Scope::MODES)]
        private int $scopeMode,
        private AbstractBeanDefinition $def,
        private bool $fromConf,
        private string $confName = '',
        private string $confMethod = '',
        private mixed $instance = null,
    )
    {
        if ($fromConf && (!$confName || !$confMethod)) {
            throw new RuntimeException('Bean in `Configuration` must set `confName`, `confMethod`');
        }
    }

    public function getInstance()
    {
        switch ($this->scope) {
            case Scope::SCOPE_SINGLETON:
                return $this->instance;
            case Scope::SCOPE_SESSION:
            case Scope::SCOPE_REQUEST:
                return $this->instance[Coroutine::getContext()['fd']];
            case Scope::SCOPE_PROTOTYPE:
            default:
                return null;
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function isPrimary(): bool
    {
        return $this->primary;
    }

    public function isSingleton(): bool
    {
        return $this->scope === Scope::SCOPE_SINGLETON;
    }

    public function isLazy(): bool
    {
        return $this->lazy;
    }

    public function getScope(): int
    {
        return $this->scope;
    }

    public function getScopeMode(): int
    {
        return $this->scopeMode;
    }

    public function getDef(): AbstractBeanDefinition
    {
        return $this->def;
    }

    public function isFromConf(): bool
    {
        return $this->fromConf;
    }

    public function getConfName(): string
    {
        return $this->confName;
    }

    public function getConfMethod(): string
    {
        return $this->confMethod;
    }

    public function setInstance($instance)
    {
        switch ($this->scope) {
            case Scope::SCOPE_SINGLETON:
                $this->instance = $instance;
                break;
            case Scope::SCOPE_SESSION:
            case Scope::SCOPE_REQUEST:
                $this->instance[Coroutine::getContext()['fd']] = $instance;
                break;
            default:
                break;
        }
    }

    /**
     * @param bool $haveType 注入的地方有无类型限定
     *                       生成代理对象
     *                       如果需要代理类替换的地方是有类型标注的，就只能生成代理类， 如果原来的类型不能继承，则抛出异常
     *                       如果没有类型标注， 直接返回匿名类代理
     */
    public function getProxyInstance(bool $haveType): object
    {
        if ($haveType) {
            $proxyClass = $this->def->getProxyClass();
            $object = new $proxyClass;
        } else {
            $object = new class {
                use LazyObject;
            };
        }

        return $object->__SET_BEAN_INFO__($this);
    }
}