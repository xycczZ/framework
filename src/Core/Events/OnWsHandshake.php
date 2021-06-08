<?php
declare(strict_types=1);

namespace Xycc\Winter\Core\Events;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Server;
use Xycc\Winter\Contract\Attributes\Lazy;
use Xycc\Winter\Event\Attributes\Event;

#[Event]
class OnWsHandshake
{
    public function __construct(
        public Request $request,
        public Response $response,
        public Server $server,
    )
    {
    }
}