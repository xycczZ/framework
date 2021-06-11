<?php
declare(strict_types=1);

namespace Xycc\Winter\Aspect;


use ReflectionAttribute;
use Xycc\Winter\Aspect\Attributes\Advise;
use Xycc\Winter\Aspect\Attributes\After;
use Xycc\Winter\Aspect\Attributes\AfterReturning;
use Xycc\Winter\Aspect\Attributes\AfterThrowing;
use Xycc\Winter\Aspect\Attributes\Around;
use Xycc\Winter\Aspect\Attributes\Aspect;
use Xycc\Winter\Aspect\Attributes\Before;
use Xycc\Winter\Aspect\Attributes\Pointcut;
use Xycc\Winter\Aspect\Factories\ProxyFactory;
use Xycc\Winter\Aspect\Processors\AfterProcessor;
use Xycc\Winter\Aspect\Processors\AfterReturningProcessor;
use Xycc\Winter\Aspect\Processors\AfterThrowingProcessor;
use Xycc\Winter\Aspect\Processors\AroundProcessor;
use Xycc\Winter\Aspect\Processors\BeforeProcessor;
use Xycc\Winter\Contract\Bootstrap;
use Xycc\Winter\Contract\Container\ContainerContract;

class AspectBoot extends Bootstrap
{
    public function boot(ContainerContract $container): void
    {
        $aspects = $container->getClassesByAttr(Aspect::class);
        $pointcutAdviseMap = [];

        $processors = [];
        // 遍历所有切面类
        foreach ($aspects as $aspect) {
            // 获取到此切面类的切点方法
            $pointcuts = $aspect->getMethods(Pointcut::class);
            // 获取到此切面类的通知方法
            $advises = $aspect->getMethods(Advise::class, true);
            $refClass = $aspect->getRefClass();

            // 遍历切点
            // 组成为一个二维数组 [表达式 =>[ [通知类型 => [切面类id, 通知方法], 通知类型 => [切面类id， 通知方法]]  ], 表达式2 => [...]]
            foreach ($pointcuts as $pointcut) {
                $pointcutMethod = $refClass->getMethod($pointcut);
                $pointcutAttr = $pointcutMethod->getAttributes(Pointcut::class);

                // 当前切点的表达式
                $expr = $pointcutAttr[0]->newInstance()->expr;
                // 当前切点的所有通知方法
                $pointcutAdvises = filter_map($advises, function (string $advise) use ($aspect, $pointcut) {
                    $adviseAttr = $aspect->getMethodAttributes($advise, Advise::class, true)[0]->newInstance();
                    $pcs = $adviseAttr->pointcuts;
                    if (!in_array($pointcut, $pcs)) {
                        return null;
                    }

                    return $advise;
                });

                if (count($pointcutAdvises) === 0) {
                    continue;
                }

                // 表达式对应的 所有 通知方法
                $pointcutAdviseMap[$expr][] = ['advises' => $pointcutAdvises, 'aspectClass' => $aspect->getClassName()];
            }

            // 将所有的切点方法收集为处理器实例
            foreach ($advises as $advise) {
                $refMethod = $aspect->getRefMethod($advise);
                $attr = $refMethod->getAttributes(Advise::class, ReflectionAttribute::IS_INSTANCEOF)[0]->newInstance();
                $instance = $container->get($aspect->getName());
                if ($attr instanceof Around) {
                    $closure = fn (...$args) => $instance->{$advise}(...$args);
                } else {
                    $closure = fn (...$args) => $instance->{$advise}(...$args);
                }
                $processors[$aspect->getClassName()][$advise] = match ($attr::class) {
                    Before::class => new BeforeProcessor($closure),
                    After::class => new AfterProcessor($closure),
                    Around::class => new AroundProcessor($closure),
                    AfterReturning::class => new AfterReturningProcessor($closure),
                    AfterThrowing::class => new AfterThrowingProcessor($closure),
                };
            }
        }

        $factory = $container->get(ProxyFactory::class);
        $factory->setPointcutAdviseMap($pointcutAdviseMap);
        $factory->setProcessors($processors);
        $factory->weaveIn();
    }

    public static function scanPath(): array
    {
        return [
            __DIR__ => __NAMESPACE__,
        ];
    }
}