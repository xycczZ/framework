<?php


namespace Xycc\Winter\Http;


use ReflectionClass;
use Xycc\Winter\Contract\Attributes\Order;
use Xycc\Winter\Contract\Bootstrap;
use Xycc\Winter\Contract\Container\ContainerContract;
use Xycc\Winter\Http\Attributes\CatchStatus;
use Xycc\Winter\Http\Attributes\ExceptionHandler;
use Xycc\Winter\Http\Attributes\HttpMiddleware;

class HttpBoot extends Bootstrap
{
    public static function scanPath(): array
    {
        return [
            __DIR__ => __NAMESPACE__,
        ];
    }

    public function boot(ContainerContract $container): void
    {
        $this->collectMiddlewares($container);
        $this->collectErrorHandlers($container);
    }

    protected function collectMiddlewares($container)
    {
        $definitions = $container->getClassesByAttr(HttpMiddleware::class);
        $custom = [];
        $global = [];
        foreach ($definitions as $definition) {
            $middleware = $definition->getRefClass()->getAttributes(HttpMiddleware::class)[0]->newInstance();
            $value = [
                'class' => $definition->getClassName(),
                'order' => $this->getOrder($definition->getRefClass()),
            ];

            if ($middleware->all) {
                $global[] = $value;
            } else {
                $custom[$middleware->group][] = $value;
            }
        }

        $custom = array_map(function (array $group) {
            usort($group, fn ($a, $b) => $a['order'] <=> $b['order']);
            return array_values($group);
        }, $custom);
        usort($global, fn ($a, $b) => $a['order'] <=> $b['order']);

        $middlewareManager = $container->get(MiddlewareManager::class);
        $middlewareManager->setCustomMiddlewares($custom);
        $middlewareManager->setGlobalMiddlewares(array_values($global));
    }

    protected function collectErrorHandlers(ContainerContract $container)
    {
        $errorHandlers = $container->getClassesByAttr(ExceptionHandler::class);
        $handlers = [];

        $manager = $container->get(ExceptionManager::class);

        foreach ($errorHandlers as $errorHandler) {
            $catchers = $errorHandler->getMethods(CatchStatus::class);
            foreach ($catchers as $catcher) {
                $catchStatus = $errorHandler->getMethodAttributes($catcher, CatchStatus::class)[0]->newInstance();
                $handlers[$catchStatus->status][$catchStatus->exception ?: 'all'][] = [$errorHandler->getClassName(), $catcher];
            }
        }

        $manager->setHandlers($handlers);
    }

    private function getOrder(ReflectionClass $ref): int
    {
        $order = $ref->getAttributes(Order::class);
        if (empty($order)) {
            return Order::DEFAULT;
        }
        return $order[0]->newInstance()->value;
    }
}