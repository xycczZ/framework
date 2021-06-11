<?php
declare(strict_types=1);

namespace Xycc\Winter\Route;

use Closure;
use Xycc\Winter\Contract\Attributes\Component;
use Xycc\Winter\Contract\Attributes\NoProxy;
use Xycc\Winter\Route\Attributes\Route;
use Xycc\Winter\Route\Exceptions\InvalidRouteException;
use Xycc\Winter\Route\Exceptions\RouteMatchException;

#[Component]
#[NoProxy]
class Router
{
    private array $routes = [
        Route::GET => null,
        Route::POST => null,
        Route::PUT => null,
        Route::DELETE => null,
        Route::PATCH => null,
        Route::OPTIONS => null,
        Route::ANY => null,
    ];

    public function __construct()
    {
        array_walk($this->routes, fn (&$route) => $route = Node::root());
    }

    /**
     * 允许自定义方法
     *
     * @throws InvalidRouteException
     */
    public function addRoute(string $method, string $path, string $group, ?string $class = null,
                             ?string $classMethod = null, ?Closure $handler = null): void
    {
        $path = str_starts_with($path, '/') ? substr($path, 1) : $path;

        $method = mb_strtolower($method);
        if (!isset($this->routes[$method])) {
            $this->routes[$method] = Node::root();
        }

        if ($handler !== null || ($class !== null && $classMethod !== null && class_exists($class) && method_exists($class, $classMethod))) {
            $this->routes[$method]->addChildren($path, $group, $class, $classMethod, $handler);
            return ;
        }

        throw new InvalidRouteException('must provide a callable or class method');
    }

    /**
     * @throws RouteMatchException
     */
    public function match(string $uri, string $method): RouteItem
    {
        $method = strtolower($method);

        if (! isset($this->routes[$method])) {
            throw new RouteMatchException('must specify a method');
        } elseif ($method === Route::ANY) {
            throw new RouteMatchException('must specify a method expect ANY');
        }

        $node = $this->routes[$method];
        /**@var Node $node*/
        try {
            $routeItem = $node->match($uri);
        } catch (RouteMatchException) {
            $routeItem = $this->routes[Route::ANY]->match($uri);
        }
        return $routeItem->setMethod($method);
    }
}