<?php
declare(strict_types=1);

namespace Xycc\Winter\Route\Exceptions;


use RuntimeException;
use Xycc\Winter\Http\Response\Response;

class RouteMatchException extends RuntimeException
{
    public function render(Response $response)
    {
        $response->setStatusCode(404);
        $response->setContent(json_encode(['error' => $this->getMessage()], JSON_UNESCAPED_UNICODE));
    }
}