<?php
declare(strict_types=1);

namespace Xycc\Winter\Core;


use Xycc\Winter\Container\Factory\BeanFactory;
use Xycc\Winter\Contract\Bootstrap;
use Xycc\Winter\Contract\Container\ContainerContract;
use Xycc\Winter\Core\Attributes\UserProcess;

class CoreBoot extends Bootstrap
{
    private static array $processes = [];

    public function boot(ContainerContract $container): void
    {
        $container->publishFiles(__DIR__ . '/Config/server.yml');
        $container->publishFiles(__DIR__ . '/Config/app.yml');

        $this->collectProcesses($container);
    }

    public function collectProcesses(ContainerContract $container)
    {
        $processes = $container->getClassesByAttr(UserProcess::class);

        $result = [];
        $factory = $container->get(BeanFactory::class);
        foreach ($processes as $process) {
            $attr = $process->getClassAttributes(UserProcess::class)[0]->newInstance();
            $instance = $factory->get($attr->value ?: $process->getClassName());
            if (method_exists($instance, 'run')) {
                $result[] = [
                    'instance' => $instance,
                    'redirect' => $attr->redirectStdinStdout,
                    'pipe' => $attr->pipeType,
                    'coroutine' => $attr->enableCoroutine,
                ];
            }
        }

        self::$processes = $result;
    }

    public static function getProcesses(): array
    {
        return self::$processes;
    }

    public static function scanPath(): array
    {
        return [
            __DIR__ => __NAMESPACE__,
        ];
    }
}