<?php


namespace Xycc\Winter\Http\Exceptions;


use RuntimeException;
use Throwable;
use Xycc\Winter\Http\Contracts\ToResponse;

class HttpException extends RuntimeException implements ToResponse
{
    public int $status = 500;
    public string $content = 'Internal Error';

    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function toResponse(): string
    {
        return json_encode([
            'status' => $this->status,
            'reason' => $this->content,
            'trace' => $this->getTrace(),
        ], JSON_UNESCAPED_UNICODE);
    }
}