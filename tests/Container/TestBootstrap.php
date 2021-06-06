<?php
declare(strict_types=1);

namespace Xycc\Winter\Tests\Container;


use Xycc\Winter\Contract\Bootstrap;
use Xycc\Winter\Contract\Container\ContainerContract;

class TestBootstrap extends Bootstrap
{
    public function boot(ContainerContract $container): void
    {
        $container->publishFiles(__DIR__ . '/../config/app.yaml');
    }

    public static function scanPath(): array
    {
        return [
            __DIR__ => __NAMESPACE__
        ];
    }
}