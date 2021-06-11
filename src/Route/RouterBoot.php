<?php
declare(strict_types=1);

namespace Xycc\Winter\Route;


use Xycc\Winter\Container\BeanDefinitions\AbstractBeanDefinition;
use Xycc\Winter\Contract\Bootstrap;
use Xycc\Winter\Contract\Container\ContainerContract;
use Xycc\Winter\Route\Attributes\Controller;
use Xycc\Winter\Route\Attributes\Route;

class RouterBoot extends Bootstrap
{
    private ContainerContract $app;

    public function boot(ContainerContract $container): void
    {
        $this->app = $container;

        $controllers = $container->getClassesByAttr(Controller::class, true);
        $router = $container->get(Router::class);

        foreach ($controllers as $controller) {
            $routes = $this->getRoutes($controller);
            foreach ($routes as $route) {
                /**@var Router $router*/
                $router->addRoute(...$route);
            }
        }
    }

    public static function scanPath(): array
    {
        return [
            __DIR__ => __NAMESPACE__
        ];
    }

    private function getRoutes(AbstractBeanDefinition $controller): array
    {
        $controllerAttr = $controller->getClassAttributes(Controller::class, true);
        $path = $controllerAttr[0]->newInstance()->path;
        $routes = $controller->getMethods(Route::class, true);
        $result = [];
        foreach ($routes as $route) {
            $method = $controller->getMethodAttributes($route, Route::class, true);
            $attr = $method[0]->newInstance();
            $result[] = [
                'method' => $attr->method,
                'path' => $this->combinePath($path, $attr->path),
                'class' => $controller->getClassName(),
                'classMethod' => $route,
                'group' => $attr->group,
            ];
        }

        return $result;
    }

    public function combinePath(string $controllerPath, string $methodPath): string
    {
        if (str_starts_with($methodPath, '/')) {
            return $methodPath;
        }
        if (! str_ends_with($controllerPath, '/')) {
            $controllerPath .= '/';
        }

        return $controllerPath . $methodPath;
    }
}