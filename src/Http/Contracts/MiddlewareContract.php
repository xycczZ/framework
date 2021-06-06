<?php


namespace Xycc\Winter\Http\Contracts;


use Closure;
use Xycc\Winter\Http\Request\Request;
use Xycc\Winter\Http\Response\Response;

interface MiddlewareContract
{
    public function handle(Request $request, Closure $next): Response;
}