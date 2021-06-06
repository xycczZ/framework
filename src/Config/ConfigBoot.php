<?php
declare(strict_types=1);

namespace Xycc\Winter\Config;


use Xycc\Winter\Contract\Bootstrap;
use Xycc\Winter\Contract\Container\ContainerContract;

class ConfigBoot extends Bootstrap
{
    public function boot(ContainerContract $container): void
    {
        $this->addPredefinedConfig($container->get(Config::class), $container->getRootPath());
    }

    private function addPredefinedConfig(Config $config, string $rootPath)
    {
        $config->setArr([
            'app.path' => $rootPath,
            'app.runtime' => $rootPath . DIRECTORY_SEPARATOR . 'runtime',
            'app.log-path' => $rootPath . DIRECTORY_SEPARATOR . 'logs',
        ]);
    }

    public static function scanPath(): array
    {
        return [
            __DIR__ => __NAMESPACE__,
        ];
    }
}