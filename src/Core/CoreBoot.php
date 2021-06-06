<?php
declare(strict_types=1);

namespace Xycc\Winter\Core;


use Xycc\Winter\Contract\Bootstrap;
use Xycc\Winter\Contract\Container\ContainerContract;

class CoreBoot extends Bootstrap
{
    public function boot(ContainerContract $container): void
    {
        $container->publishFiles(__DIR__ . '/config/server.yml');
    }

    public static function scanPath(): array
    {
        return [
            __DIR__ => __NAMESPACE__,
        ];
    }
}