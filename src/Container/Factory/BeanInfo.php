<?php


namespace Xycc\Winter\Container\Factory;


use JetBrains\PhpStorm\ExpectedValues;
use RuntimeException;
use Swoole\Coroutine;
use Xycc\Winter\Container\BeanDefinitions\AbstractBeanDefinition;
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
        return match ($this->scope) {
            Scope::SCOPE_SINGLETON => $this->instance,
            Scope::SCOPE_SESSION, Scope::SCOPE_REQUEST => $this->instance[Coroutine::getContext()['fd']] ?? null,
            default => null,
        };
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

    public function clearRequest()
    {
        $this->instance[Coroutine::getContext()['fd']] = null;
    }

    public function clearSession()
    {
        $this->instance[Coroutine::getContext()['fd']] = null;
    }
}