<?php
declare(strict_types=1);

namespace Xycc\Winter\Contract;


use Xycc\Winter\Contract\Container\ContainerContract;

abstract class Bootstrap
{
    /**
     * 模块启动时的处理
     */
    public abstract function boot(ContainerContract $container): void;

    /**
     * 本模块需要扫描的路径
     *
     * @return string[] 需要扫描的路径, 绝对路径
     */
    public static function scanPath(): array
    {
        return [
            // __DIR__ => __NAMESPACE__
        ];
    }

    public static function exclude(): array
    {
        return [
            // dir
            // file
        ];
    }
}