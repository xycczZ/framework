<?php


namespace Xycc\Winter\Http\Response;

use Swoole\Http\Response as SwooleResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Xycc\Winter\Contract\Attributes\Component;
use Xycc\Winter\Contract\Attributes\NoProxy;
use Xycc\Winter\Contract\Attributes\Scope;

/**
 * @mixin SymfonyResponse
 */
#[Component, NoProxy]
#[Scope(Scope::SCOPE_REQUEST, Scope::MODE_PROXY)]
class Response extends SymfonyResponse
{
    // swoole response only used on send
    protected SwooleResponse $swooleResponse;

    public static function create(int $fd): self
    {
        $self = new self();
        $self->swooleResponse = SwooleResponse::create($fd);
        return $self;
    }

    public function setSwooleResponse(SwooleResponse $response): self
    {
        $this->swooleResponse = $response;
        return $this;
    }

    public function download(string $file, int $offset = 0, int $length = 0): bool
    {
        return $this->swooleResponse->sendfile($file, $offset, $length);
    }

    public function upgrade()
    {
        return $this->swooleResponse->upgrade();
    }

    public function detach()
    {
        return $this->swooleResponse->detach();
    }

    public function send()
    {
        $this->swooleResponse->status($this->getStatusCode(), self::$statusTexts[$this->getStatusCode()]);

        foreach ($this->headers->all() as $key => $item) {
            foreach ($item as $value) {
                $this->swooleResponse->header($key, $value);
            }
        }

        return $this->swooleResponse->end($this->content);
    }
}