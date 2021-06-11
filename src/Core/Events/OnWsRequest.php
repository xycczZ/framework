<?php
declare(strict_types=1);

namespace Xycc\Winter\Core\Events;

use Swoole\Http\Response;
use Xycc\Winter\Event\Attributes\Event;
use Xycc\Winter\Http\Request\Request;

#[Event]
class OnWsRequest
{
    public function __construct(
        public Request $request,
        public Response $response,
    )
    {
    }
}