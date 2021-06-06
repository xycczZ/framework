<?php


namespace Xycc\Winter\Http\Middleware;


use Closure;
use Xycc\Winter\Contract\Attributes\Order;
use Xycc\Winter\Http\Attributes\HttpMiddleware;
use Xycc\Winter\Http\Contracts\MiddlewareContract;
use Xycc\Winter\Http\Request\Request;
use Xycc\Winter\Http\Response\Response;

#[Order(11)]
#[HttpMiddleware(all: true)]
class DefaultContentTypeMiddleware implements MiddlewareContract
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->header('Accept-Type', 'application/json');
        $response = $next($request);
        /**@var Response $response */
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
}