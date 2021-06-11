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
class Request
{
    protected SymfonyRequest $symfonyRequest;
    protected SwooleRequest $swooleRequest;

    public function init(SwooleRequest $request)
    {
        $this->swooleRequest = $request;
        $servers = $this->prepareServers($request->server, $request->header);

        $this->symfonyRequest = new SymfonyRequest($this->swooleRequest->get ?? [], $this->swooleRequest->post ?? [], [], $this->swooleRequest->cookie ?? [], $this->swooleRequest->files ?? [], $servers, $this->swooleRequest->rawContent());

        SymfonyRequest::enableHttpMethodParameterOverride();
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

    public function query(string $key = '', mixed $default = null): mixed
    {
        if ($key === '') {
            return $this->symfonyRequest->query->all();
        }
        return $this->symfonyRequest->query->get($key, $default);
    }

    public function post(string $key = '', mixed $default = null): mixed
    {
        if ($key === '') {
            return $this->symfonyRequest->request->all();
        }
        return $this->symfonyRequest->request->get($key, $default);
    }

    public function file(string $key = '', mixed $default = null): mixed
    {
        if ($key === '') {
            return $this->symfonyRequest->files->all();
        }
        return $this->symfonyRequest->files->get($key, $default);
    }

    public function server(string $key = '', mixed $default = null): mixed
    {
        if ($key === '') {
            return $this->symfonyRequest->server->all();
        }
        return $this->symfonyRequest->server->get($key, $default);
    }

    public function header(string $key = '', mixed $default = null): mixed
    {
        if ($key === '') {
            return $this->symfonyRequest->headers->all();
        }
        return $this->symfonyRequest->headers->get($key, $default);
    }

    public function cookie(string $key = '', mixed $default = null): mixed
    {
        if ($key === '') {
            return $this->symfonyRequest->cookies->all();
        }
        return $this->symfonyRequest->cookies->get($key, $default);
    }

    /**
     * @param string     $key
     * @param mixed|null $default
     * @return mixed
     */
    public function attribute(string $key = '', mixed $default = null): mixed
    {
        if ($key === '') {
            return $this->symfonyRequest->attributes->all();
        }
        return $this->symfonyRequest->attributes->get($key, $default);
    }

    public function request(string $key = '', mixed $default = null): mixed
    {
        if ($key === '') {
            return array_merge($this->symfonyRequest->attributes, $this->symfonyRequest->query, $this->symfonyRequest->request);
        }
        return $this->symfonyRequest->get($key, $default);
    }

    public function getRequest(): SymfonyRequest
    {
        return $this->symfonyRequest;
    }

    public function __call($method, $args)
    {
        return $this->symfonyRequest->{$method}(...$args);
    }

    public function __get(string $name)
    {
        return $this->symfonyRequest->{$name};
    }
}