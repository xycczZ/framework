<?php


namespace Xycc\Winter\Container\Factory;


use JetBrains\PhpStorm\ExpectedValues;
use RuntimeException;
use Xycc\Winter\Container\BeanDefinitions\AbstractBeanDefinition;
use Xycc\Winter\Contract\Attributes\Scope;

class BeanInfo
{
    public function __construct(
        public string $name,
        public int $order,
        public bool $primary,
        public bool $lazy,
        #[ExpectedValues(flags: Scope::SCOPES)]
        public int $scope,
        #[ExpectedValues(flags: Scope::MODES)]
        public int $scopeMode,
        public AbstractBeanDefinition $def,
        public bool $fromConf,
        public string $confName = '',
        public string $confMethod = '',
        public $instance = null,
    )
    {
        if ($fromConf && (!$confName || !$confMethod)) {
            throw new RuntimeException('Bean in `Configuration` must set `confName`, `confMethod`');
        }
    }
}