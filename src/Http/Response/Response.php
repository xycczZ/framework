<?php


namespace Xycc\Winter\Http\Response;

use Stringable;
use Swoole\Http\Response as SwooleResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Xycc\Winter\Contract\Attributes\Component;
use Xycc\Winter\Contract\Attributes\NoProxy;
use Xycc\Winter\Contract\Attributes\Scope;
use Xycc\Winter\Http\Contracts\ToResponse;

/**
 * @mixin SymfonyResponse
 */
#[Component, NoProxy]
#[Scope(Scope::SCOPE_REQUEST, Scope::MODE_PROXY)]
class Response
{
    protected array|string|int|float|bool|ToResponse|Stringable $content = '';
    protected SymfonyResponse $response;
    // swoole response only used on send
    protected SwooleResponse $swooleResponse;

    public function __construct()
    {
        $this->response = new SymfonyResponse();
    }

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

    public function getContent()
    {
        return $this->content;
    }

    public function setContent(array|string|int|float|bool|ToResponse|Stringable $content): self
    {
        $this->content = $content;
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
        $this->swooleResponse->status($this->response->getStatusCode(), SymfonyResponse::$statusTexts[$this->response->getStatusCode()]);
        //$this->swooleResponse->header = $this->response->headers->all();
        //$this->swooleResponse->cookie = array_map(fn (Cookie $cookie) => (string)$cookie, $this->response->headers->getCookies());
        foreach ($this->response->headers->all() as $key => $item) {
            foreach ($item as $value) {
                $this->swooleResponse->header($key, $value);
            }
        }

        if (is_array($this->content)) {
            $content = json_encode($this->content, JSON_UNESCAPED_UNICODE);
        } elseif ($this->content instanceof ToResponse) {
            $content = $this->content->toResponse();
        } elseif (is_scalar($this->content)) {
            $content = $this->content;
        } else {
            $content = (string)$this->content;
        }

        return $this->swooleResponse->end($content);
    }

    public function __call($method, $args)
    {
        return $this->response->{$method}(...$args);
    }

    public function __get($name)
    {
        return $this->response->{$name};
    }
}