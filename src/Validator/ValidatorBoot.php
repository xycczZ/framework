<?php


namespace Xycc\Winter\Validator;


use Xycc\Winter\Contract\Bootstrap;
use Xycc\Winter\Contract\Container\ContainerContract;

class ValidatorBoot extends Bootstrap
{
    public function boot(ContainerContract $container): void
    {

    }

    public static function scanPath(): array
    {
        return [
            __DIR__ => __NAMESPACE__,
        ];
    }
}