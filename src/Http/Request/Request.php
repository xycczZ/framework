<?php


namespace Xycc\Winter\Http\Request;

use Swoole\Http\Request as SwooleRequest;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Xycc\Winter\Contract\Attributes\Component;
use Xycc\Winter\Contract\Attributes\NoProxy;
use Xycc\Winter\Contract\Attributes\Scope;

/**
 * @mixin SymfonyRequest
 */
#[Component, NoProxy]
#[Scope(Scope::SCOPE_REQUEST, Scope::MODE_PROXY)]
class Request extends SymfonyRequest
{

    public int $fd;
    public int $streamId;
    public $tmpfiles;

    public function init(SwooleRequest $request)
    {
        $servers = $this->prepareServers($request->server, $request->header);

        $this->initialize($request->get ?: [], $request->post ?: [], [], $request->cookie ?: [], $request->files ?: [],
            $servers, $request->getContent());

        $this->fd = $request->fd;
        $this->streamId = $request->streamId;
        $this->tmpfiles = $request->tmpfiles;

        self::enableHttpMethodParameterOverride();
    }

    private function prepareServers(array $servers = [], array $headers = []): array
    {
        $result = [];
        foreach ($servers as $key => $value) {
            $result[mb_strtoupper($key)] = $value;
        }
        foreach ($headers as $key => $value) {
            $result['HTTP_' . mb_strtoupper($key)] = $value;
        }
        return $result;
    }
}