<?php
declare(strict_types=1);

namespace Xycc\Winter\Container;


use Xycc\Winter\Contract\Bootstrap;
use Xycc\Winter\Contract\Container\ContainerContract;

class ContainerBoot extends Bootstrap
{
    public function boot(ContainerContract $container): void
    {
        $container->publishFiles(__DIR__ . '/config/app.yaml');
    }

    public static function scanPath(): array
    {
        return [
            __DIR__ => __NAMESPACE__,
        ];
    }

    public static function exclude(): array
    {
        return [
            __DIR__ . '/Application.php',
            __DIR__ . '/BeanDefinitionCollection.php',
            __DIR__ . '/ClassLoader.php',
            __DIR__ . '/Proxy/ProxyManager.php',
        ];
    }
}