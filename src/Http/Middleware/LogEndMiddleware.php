<?php


namespace Xycc\Winter\Http\Middleware;


use Closure;
use Xycc\Winter\Http\Attributes\HttpMiddleware;
use Xycc\Winter\Http\Contracts\MiddlewareContract;
use Xycc\Winter\Http\Request\Request;
use Xycc\Winter\Http\Response\Response;

#[HttpMiddleware(all: true)]
class LogEndMiddleware implements MiddlewareContract
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        /**@var Response $response */
        $response = $next($request);
        $end = microtime(true);

        echo sprintf('[%s] %s %d : use %.4f ms',
            $request->getMethod(),
            $request->getRequestUri(),
            $response->getStatusCode(),
            ($end - $start)
        ), PHP_EOL;

        return $response;
    }
}