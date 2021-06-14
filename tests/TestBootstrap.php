<?php


namespace Xycc\Winter\Tests;


use Xycc\Winter\Contract\Bootstrap;
use Xycc\Winter\Contract\Container\ContainerContract;

class TestBootstrap extends Bootstrap
{
    public function boot(ContainerContract $container): void
    {
        $_ENV['winter.app_env'] = 'test';
    }

    public static function scanPath(): array
    {
        return [
            __DIR__ => __NAMESPACE__,
        ];
    }
}