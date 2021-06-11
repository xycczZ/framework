<?php
declare(strict_types=1);

namespace Xycc\Winter\Core;


use Swoole\Http\Request;

class SerializedRequest
{
    public $fd = 0;

    public $header;

    public $server;

    public $cookie;

    public $get;

    public $files;

    public $post;

    public $tmpfiles;

    public static function fromRequest(Request $request): self
    {
        $req = new self();
        foreach (['fd', 'header', 'server', 'cookie', 'get', 'files', 'post', 'tmpfiles'] as $item) {
            $req->{$item} = $request->{$item};
        }

        return $req;
    }

    public function __serialize()
    {
        return [
            'fd' => $this->fd,
            'header' => $this->header,
            'server' => $this->server,
            'cookie' => $this->cookie,
            'get' => $this->get,
            'files' => $this->files,
            'post' => $this->post,
            'tmpfiles' => $this->tmpfiles,
        ];
    }

    public function __unserialize(array $data): void
    {
        foreach (['fd', 'header', 'server', 'cookie', 'get', 'files', 'post', 'tmpfiles'] as $item) {
            $this->{$item} = $data[$item];
        }
    }
}