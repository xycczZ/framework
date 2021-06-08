<?php
declare(strict_types=1);

namespace Xycc\Winter\Core\Servers;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Server;
use Xycc\Winter\Container\Application;
use Xycc\Winter\Contract\Attributes\Bean;
use Xycc\Winter\Core\Events\OnWsHandshake;
use Xycc\Winter\Event\EventDispatcher;

#[Bean]
class WebsocketServer
{
    private Server $server;
    private array $settings = [];

    public function __construct(
        private Application $app,
        private EventDispatcher $dispatcher,
    )
    {
    }

    public function start(array $serverConfig)
    {
        $this->settings = $serverConfig['settings'];
        $server = new Server($serverConfig['host'], $serverConfig['port']);
        $this->server = $server;
        $this->server->set($this->settings);
        $this->server->on('handshake', [$this, 'onHandshake']);
        $this->server->on('request', [$this, 'onRequest']);
    }

    public function onHandshake(Request $request, Response $response)
    {
        $this->dispatcher->dispatch(new OnWsHandshake($request, $response, $this->server));
    }

    public function onRequest(Request $request, Response $response)
    {

    }
}